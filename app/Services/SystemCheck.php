<?php

namespace App\Services;

use App\Http\Middleware\RunDailyCleanup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * What the owner's System page reports: whether this server fits the app.
 * It stands in for SSH on hosting without a command line.
 */
class SystemCheck
{
    /** PHP extensions the app needs (bcmath does all the money arithmetic). */
    public const EXTENSIONS = ['bcmath', 'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'tokenizer', 'ctype', 'curl', 'dom'];

    /** Folders PHP must be able to write to. */
    public const WRITABLE = ['storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/public', 'bootstrap/cache'];

    /**
     * @return array<string, mixed>
     */
    public function report(Request $request): array
    {
        return [
            'php' => PHP_VERSION,
            'php_ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'extensions' => collect(self::EXTENSIONS)->mapWithKeys(fn (string $ext) => [$ext => extension_loaded($ext)])->all(),
            'writable' => collect(self::WRITABLE)->mapWithKeys(fn (string $dir) => [$dir => is_writable(base_path($dir))])->all(),

            // If visitor_ip shows the proxy's address, set TRUSTED_PROXIES=* (see config/trustedproxy.php).
            'visitor_ip' => $request->ip(),
            'remote_addr' => (string) $request->server('REMOTE_ADDR'),
            'forwarded_for' => $request->headers->get('X-Forwarded-For'),
            'https' => $request->secure(),

            'environment' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => (string) config('app.url'),
            'drivers' => [
                'session' => config('session.driver'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'mail' => config('mail.default'),
            ],
            'payments_online' => (bool) config('bookease.payments.online'),
            'payment_driver' => (string) config('bookease.payments.driver'),

            'pending_migrations' => $this->pendingMigrations(),
            'cleanup_on_requests' => (bool) config('bookease.maintenance.on_requests'),
            'last_cleanup' => Cache::get(RunDailyCleanup::LAST_RUN_KEY),
        ];
    }

    /**
     * Migration files not yet applied to this database ("database updates").
     *
     * @return list<string>|null null when the database can't be read
     */
    private function pendingMigrations(): ?array
    {
        try {
            $migrator = app('migrator');

            if (! $migrator->repositoryExists()) {
                return array_keys($migrator->getMigrationFiles(database_path('migrations')));
            }

            $files = $migrator->getMigrationFiles(database_path('migrations'));

            return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
        } catch (Throwable) {
            return null;
        }
    }
}
