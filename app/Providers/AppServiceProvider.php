<?php

namespace App\Providers;

use App\Payments\FakeGateway;
use App\Payments\PaymentGateway;
use App\Payments\PayMongoGateway;
use Illuminate\Contracts\Foundation\Application;
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
    }

    public function boot(): void
    {
        //
    }
}
