<?php

namespace App\Chatbot;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Gemini generateContent REST endpoint.
 * It has no tools or function calling: the model can only return text.
 */
class GeminiClient
{
    /** Finish reasons that mean the answer was withheld by Google's filters. */
    private const BLOCKED_FINISH = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        private readonly int $timeout = 20,
        private readonly int $maxOutputTokens = 1024,
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

        $url = rtrim($this->baseUrl, '/').'/models/'.rawurlencode($this->model).':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout($this->timeout)
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
                ]);
        } catch (ConnectionException $e) {
            throw new LanguageModelUnavailable('Gemini could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new LanguageModelUnavailable("Gemini returned HTTP {$response->status()}.");
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
            throw new LanguageModelUnavailable('Gemini returned an empty answer'.($finish ? " ({$finish})" : '').'.');
        }

        return $text;
    }
}
