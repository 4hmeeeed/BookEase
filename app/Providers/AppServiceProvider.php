<?php

namespace App\Providers;

use App\Chatbot\GeminiClient;
use App\Models\Booking;
use App\Models\Cottage;
use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use App\Payments\PayMongoGateway;
use App\Services\ChatbotService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function (Application $app) {
            $cfg = config('bookease.payments');

            return match ($cfg['driver']) {
                'paymongo' => new PayMongoGateway(
                    secretKey: config('services.paymongo.secret_key'),
                    webhookSecret: config('services.paymongo.webhook_secret'),
                    baseUrl: config('services.paymongo.base_url'),
                    toleranceSeconds: $cfg['webhook_tolerance_seconds'],
                    allowLiveMode: $cfg['allow_live_mode'],
                ),
                'fake' => $app->isProduction()
                    ? throw new RuntimeException('The fake payment gateway cannot be used in production.')
                    : new FakeGateway(
                        secretKey: null,
                        webhookSecret: $cfg['fake_webhook_secret'] ?: hash_hmac('sha256', 'fake-gateway', (string) config('app.key')),
                        toleranceSeconds: $cfg['webhook_tolerance_seconds'],
                        allowLiveMode: false,
                    ),
                default => throw new RuntimeException("Unknown payment driver [{$cfg['driver']}]."),
            };
        });

        $this->app->bind(GeminiClient::class, fn () => new GeminiClient(
            apiKey: config('services.gemini.api_key'),
            model: (string) config('services.gemini.model'),
            baseUrl: (string) config('services.gemini.base_url'),
            timeout: (int) config('services.gemini.timeout', 20),
            maxOutputTokens: (int) config('services.gemini.max_output_tokens', 1024),
            fallbackModels: array_values((array) config('services.gemini.fallback_models', [])),
            totalTimeout: (int) config('services.gemini.total_timeout', 40),
        ));
    }

    public function boot(): void
    {
        // Chatbot: 10 questions a minute (thesis security plan) plus a daily cap,
        // keyed by account when signed in, otherwise by IP address.
        RateLimiter::for('chatbot', function (Request $request) {
            $key = $request->user() ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $tooMany = fn () => response()->json([
                'message' => 'You’re sending questions quickly. Please wait a moment and try again.',
            ], 429);

            return [
                Limit::perMinute((int) config('bookease.chatbot.per_minute', 10))->by('min:'.$key)->response($tooMany),
                Limit::perDay((int) config('bookease.chatbot.per_day', 150))->by('day:'.$key)->response($tooMany),
            ];
        });

        // Sign-ups and reset emails, per IP address. Each guest account may hold
        // only a few pending requests, so scripted sign-ups must be limited too.
        // Generous, because many mobile users share one carrier IP address.
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by('register:'.$request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by('reset:'.$request->ip()));

        // The chatbot must never quote stale rates or availability.
        foreach ([Cottage::class, Booking::class] as $model) {
            $model::saved(fn () => ChatbotService::forgetContext());
            $model::deleted(fn () => ChatbotService::forgetContext());
        }
    }
}
