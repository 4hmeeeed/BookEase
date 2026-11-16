<?php

namespace Tests\Feature;

use App\Chatbot\GeminiClient;
use App\Chatbot\LanguageModelRefused;
use App\Chatbot\LanguageModelUnavailable;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * When the configured Gemini model is overloaded, timing out or retired, the
 * question goes to the next configured model instead of failing outright.
 * Problems another model can't fix (a bad key, a safety block) are not retried.
 */
class GeminiFallbackTest extends TestCase
{
    private const PRIMARY = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-flash:generateContent';

    private const BACKUP = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test-lite:generateContent';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.api_key' => 'test-gemini-key',
            'services.gemini.model' => 'gemini-test-flash',
            'services.gemini.fallback_models' => ['gemini-test-lite'],
            'services.gemini.total_timeout' => 40,
        ]);
    }

    /** @return array<string, mixed> */
    private static function answer(string $text): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']]];
    }

    private function generate(): string
    {
        return app(GeminiClient::class)->generate('Answer briefly.', [['role' => 'user', 'text' => 'Hi']]);
    }

    private function backupWasCalled(): bool
    {
        return Http::recorded(fn (Request $r) => $r->url() === self::BACKUP)->isNotEmpty();
    }

    /** @return array<string, array{Closure}> */
    public static function busyPrimaryResponses(): array
    {
        return [
            'overloaded (503)' => [fn () => Http::response(['error' => ['message' => 'This model is currently experiencing high demand.']], 503)],
            'rate limited (429)' => [fn () => Http::response(['error' => ['message' => 'Quota exceeded.']], 429)],
            'retired model (404)' => [fn () => Http::response(['error' => ['message' => 'This model is no longer available.']], 404)],
            'timed out' => [fn () => throw new ConnectionException('Operation timed out')],
            'only thoughts, no answer' => [fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => '…', 'thought' => true]]], 'finishReason' => 'MAX_TOKENS']]])],
        ];
    }

    #[DataProvider('busyPrimaryResponses')]
    public function test_busy_primary_model_hands_the_question_to_the_backup_model(Closure $busy): void
    {
        Http::fake([
            self::PRIMARY => $busy,
            self::BACKUP => Http::response(self::answer('Hello from the backup model.')),
        ]);

        $this->assertSame('Hello from the backup model.', $this->generate());
    }

    public function test_healthy_primary_model_does_not_call_the_backup(): void
    {
        Http::fake([
            self::PRIMARY => Http::response(self::answer('Hello!')),
            self::BACKUP => Http::response(self::answer('Hello from the backup model.')),
        ]);

        $this->assertSame('Hello!', $this->generate());
        $this->assertFalse($this->backupWasCalled());
    }

    /** @return array<string, array{int}> */
    public static function problemsAnotherModelCannotFix(): array
    {
        return ['malformed request' => [400], 'bad key' => [401], 'no permission' => [403]];
    }

    #[DataProvider('problemsAnotherModelCannotFix')]
    public function test_request_or_key_problems_are_not_retried_on_another_model(int $status): void
    {
        Http::fake([
            self::PRIMARY => Http::response(['error' => ['message' => 'API key not valid.']], $status),
            self::BACKUP => Http::response(self::answer('Hello from the backup model.')),
        ]);

        try {
            $this->generate();
            $this->fail('Expected the model to be unavailable.');
        } catch (LanguageModelUnavailable) {
            $this->assertFalse($this->backupWasCalled());
        }
    }

    public function test_safety_block_is_not_retried_on_another_model(): void
    {
        Http::fake([
            self::PRIMARY => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]),
            self::BACKUP => Http::response(self::answer('Hello from the backup model.')),
        ]);

        try {
            $this->generate();
            $this->fail('Expected the safety block to stand.');
        } catch (LanguageModelRefused) {
            $this->assertFalse($this->backupWasCalled());
        }
    }

    public function test_when_every_model_is_busy_the_model_is_unavailable(): void
    {
        Http::fake([
            self::PRIMARY => Http::response(['error' => ['message' => 'high demand']], 503),
            self::BACKUP => Http::response(['error' => ['message' => 'high demand']], 503),
        ]);

        try {
            $this->generate();
            $this->fail('Expected the model to be unavailable.');
        } catch (LanguageModelUnavailable) {
            $this->assertTrue($this->backupWasCalled());
        }
    }

    public function test_no_backup_attempt_once_the_time_budget_is_spent(): void
    {
        config(['services.gemini.total_timeout' => 0]);

        Http::fake([
            self::PRIMARY => Http::response(['error' => ['message' => 'high demand']], 503),
            self::BACKUP => Http::response(self::answer('Hello from the backup model.')),
        ]);

        try {
            $this->generate();
            $this->fail('Expected the model to be unavailable.');
        } catch (LanguageModelUnavailable) {
            $this->assertFalse($this->backupWasCalled());
        }
    }
}
