<?php

use App\Http\Middleware\DemoGuard;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SiteGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'plan.feature' => EnsurePlanFeature::class,
            'install.guard' => EnsureNotInstalled::class,
        ]);

        // Until the site is installed, route everything to the web installer.
        $middleware->prependToGroup('web', EnsureInstalled::class);

        // Site-wide gate: registration toggle + maintenance mode (admin-controlled).
        $middleware->appendToGroup('web', SiteGate::class);

        // Demo mode: block destructive/config-changing writes (no-op on real installs).
        $middleware->appendToGroup('web', DemoGuard::class);

        // Resolve the active UI language per request (user / cookie / default).
        $middleware->appendToGroup('web', SetLocale::class);
        // The language preference is non-sensitive and read before the session boots.
        $middleware->encryptCookies(except: ['lf_locale']);

        // Baseline security response headers on every web response.
        $middleware->appendToGroup('web', SecurityHeaders::class);

        // Payment gateways POST signed webhooks from outside — exempt them from CSRF.
        $middleware->validateCsrfTokens(except: ['billing/webhook/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
