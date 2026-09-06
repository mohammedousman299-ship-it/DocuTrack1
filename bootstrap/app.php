<?php

use App\Http\Middleware\EnsureAdminTwoFactor;
use App\Http\Middleware\EnsurePhoneVerified;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyInternalSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // Le groupe de middleware est déclaré dans le fichier de routes
            // lui-même, pour que la protection soit lisible à côté des routes.
            Route::group([], base_path('routes/internal.php'));
        },
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('internal', [
            VerifyInternalSecret::class,
        ]);

        // Garde la recherche, le signalement et la revendication : sans
        // téléphone vérifié, les quotas par compte seraient décoratifs (D-013).
        // En-têtes de sécurité sur toutes les réponses web (§4.6, D-025).
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'phone.verified' => EnsurePhoneVerified::class,
            'admin.2fa' => EnsureAdminTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
