<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Console alerts are kept only as long as they're useful (Data Privacy Act):
// once read, they go after 90 days. Unread ones wait until someone reads them.
Artisan::command('notifications:prune-read', function () {
    $deleted = DatabaseNotification::query()
        ->whereNotNull('read_at')
        ->where('read_at', '<', now()->subDays(90))
        ->delete();

    $this->info("Deleted {$deleted} read ".Str::plural('notification', $deleted).' older than 90 days.');
})->purpose('Delete console alerts that were read more than 90 days ago');

Schedule::command('notifications:prune-read')->daily();
