<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Signed-out visitors to staff or owner pages go to the staff sign-in page.
        $middleware->redirectGuestsTo(fn (\Illuminate\Http\Request $request) => \App\Support\LoginPage::for($request));

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Authenticated by the gateway's HMAC signature instead of a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The bell's background requests want a status code, not the sign-in page. Its
        // dropdown asks for HTML, so a signed-out request would otherwise be redirected.
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request) => $request->expectsJson()
            || $request->routeIs('staff.notifications.poll', 'staff.notifications.panel'));
    })->create();
