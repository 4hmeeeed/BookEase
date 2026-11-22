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
// once read, they go after 90 days. One nobody opened within a year is no
// longer actionable, so it goes too rather than keeping a guest's name forever.
Artisan::command('notifications:prune-read', function () {
    $read = DatabaseNotification::query()
        ->whereNotNull('read_at')
        ->where('read_at', '<', now()->subDays(90))
        ->delete();

    $unread = DatabaseNotification::query()
        ->whereNull('read_at')
        ->where('created_at', '<', now()->subDays(365))
        ->delete();

    $this->info("Deleted {$read} read ".Str::plural('notification', $read).' older than 90 days'
        ." and {$unread} unread older than a year.");
})->purpose('Delete console alerts read more than 90 days ago, or left unread for a year');

Schedule::command('notifications:prune-read')->daily();
