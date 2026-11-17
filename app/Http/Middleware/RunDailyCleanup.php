<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * For hosting without cron (InfinityFree): once a page has been sent, run the
 * daily clean-up, at most once a day. On hosts with cron, keep
 * BOOKEASE_CLEANUP_ON_REQUESTS=false and let the scheduler do it.
 */
class RunDailyCleanup
{
    public const LAST_RUN_KEY = 'maintenance:daily-cleanup';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('bookease.maintenance.on_requests')) {
            return;
        }

        // Only the first request of the day gets to add the key.
        if (Cache::add(self::LAST_RUN_KEY, now()->toIso8601String(), now()->addDay())) {
            Artisan::call('notifications:prune-read');
        }
    }
}
