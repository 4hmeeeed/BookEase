<?php

namespace App\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Gemini generateContent REST endpoint.
 * It has no tools or function calling: the model can only return text.
 *
 * When the configured model is overloaded, timing out or retired, the same
 * request goes to the next fallback model, within an overall time budget.
 */
class GeminiClient
{
    /** Finish reasons that mean the answer was withheld by Google's filters. */
    private const BLOCKED_FINISH = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    /** Harm categories blocked at medium probability and above. */
    private const SAFETY_CATEGORIES = [
        'HARM_CATEGORY_HARASSMENT',
        'HARM_CATEGORY_HATE_SPEECH',
        'HARM_CATEGORY_SEXUALLY_EXPLICIT',
        'HARM_CATEGORY_DANGEROUS_CONTENT',
    ];

    /** HTTP statuses another model may not have: not found/retired, rate limited, overloaded. */
    private const BUSY_STATUSES = [404, 408, 429, 500, 502, 503, 504];

    /** Don't start another model with less time than this left in the budget. */
    private const MIN_ATTEMPT_SECONDS = 3;

    /**
     * @param  list<string>  $fallbackModels  tried in order when a model is busy
     * @param  int  $totalTimeout  seconds for all attempts together
     */
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        private readonly int $timeout = 20,
        private readonly int $maxOutputTokens = 1024,
        private readonly array $fallbackModels = [],
        private readonly int $totalTimeout = 40,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @param  list<array{role: 'user'|'model', text: string}>  $turns
     *
     * @throws LanguageModelUnavailable
     * @throws LanguageModelRefused
     */
    public function generate(string $systemInstruction, array $turns): string
    {
        if (! $this->isConfigured()) {
            throw new LanguageModelUnavailable('No Gemini API key is configured.');
        }

        $models = array_values(array_unique(array_filter([$this->model, ...$this->fallbackModels])));
        $deadline = microtime(true) + $this->totalTimeout;
        $busy = null;

        foreach ($models as $i => $model) {
            $remaining = (int) floor($deadline - microtime(true));

            if ($i > 0 && $remaining < self::MIN_ATTEMPT_SECONDS) {
                break;
            }

            try {
                return $this->generateWith($model, $systemInstruction, $turns, max(1, min($this->timeout, $remaining)));
            } catch (LanguageModelBusy $e) {
                $busy = $e;

                if (isset($models[$i + 1])) {
                    Log::info("Gemini model {$model} is busy; trying {$models[$i + 1]}.", ['reason' => $e->getMessage()]);
                }
            }
        }

        throw $busy ?? new LanguageModelUnavailable('No Gemini model is configured.');
    }

    /**
     * @param  list<array{role: 'user'|'model', text: string}>  $turns
     *
     * @throws LanguageModelBusy another model might answer
     * @throws LanguageModelUnavailable
     * @throws LanguageModelRefused
     */
    private function generateWith(string $model, string $systemInstruction, array $turns, int $timeout): string
    {
        $url = rtrim($this->baseUrl, '/').'/models/'.rawurlencode($model).':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($timeout)
                ->post($url, [
                    'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => array_map(
                        fn (array $turn) => ['role' => $turn['role'], 'parts' => [['text' => $turn['text']]]],
                        $turns,
                    ),
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => $this->maxOutputTokens,
                    ],
                    // Explicit, so a change in Google's defaults can't loosen them.
                    'safetySettings' => array_map(
                        fn (string $category) => ['category' => $category, 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        self::SAFETY_CATEGORIES,
                    ),
                ]);
        } catch (ConnectionException $e) {
            throw new LanguageModelBusy("Gemini ({$model}) could not be reached: ".$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $message = "Gemini ({$model}) returned HTTP {$response->status()}.";

            throw in_array($response->status(), self::BUSY_STATUSES, true)
                ? new LanguageModelBusy($message)
                : new LanguageModelUnavailable($message);
        }

        if ($response->json('promptFeedback.blockReason')) {
            throw new LanguageModelRefused('Prompt blocked: '.$response->json('promptFeedback.blockReason'));
        }

        $candidate = $response->json('candidates.0');
        $finish = $candidate['finishReason'] ?? null;

        if (in_array($finish, self::BLOCKED_FINISH, true)) {
            throw new LanguageModelRefused("Answer blocked: {$finish}");
        }

        // Skip "thought" parts some models return alongside the answer.
        $text = collect($candidate['content']['parts'] ?? [])
            ->reject(fn ($part) => ! empty($part['thought']))
            ->pluck('text')
            ->filter(fn ($t) => is_string($t))
            ->implode('');

        $text = trim($text);

        if ($text === '') {
            throw new LanguageModelBusy("Gemini ({$model}) returned an empty answer".($finish ? " ({$finish})" : '').'.');
        }

        return $text;
    }
}
