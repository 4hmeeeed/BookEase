<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * InfinityFree's firewall answers any URL containing "chat" with a 403 before
 * Laravel sees it, which silently broke the assistant on the live site.
 */
class HostingCompatibilityTest extends TestCase
{
    public function test_no_route_path_contains_the_word_chat(): void
    {
        $blocked = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains(strtolower($uri), 'chat'))
            ->values()
            ->all();

        $this->assertSame([], $blocked, 'These paths would be blocked on InfinityFree: '.implode(', ', $blocked));
    }
}
