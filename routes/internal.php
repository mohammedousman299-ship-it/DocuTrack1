<?php

declare(strict_types=1);

use App\Http\Controllers\Internal\InternalTaskController;
use Illuminate\Support\Facades\Route;

/*
 | Endpoints internes déclenchés par un ordonnanceur externe (§3.1).
 |
 | Ils remplacent le worker de queue et le scheduler, qui ne survivent pas sur
 | une plateforme à conteneurs éphémères. Tous sont protégés par secret
 | d'en-tête, verrouillés, bornés en temps et idempotents.
 |
 | Ils ne portent PAS de session ni de cookie : ce ne sont pas des routes web.
 */

Route::middleware('internal')->prefix('internal')->group(function (): void {
    Route::post('queue/drain', [InternalTaskController::class, 'drainQueue']);
    Route::post('cron/match', [InternalTaskController::class, 'match']);
    Route::post('cron/notify', [InternalTaskController::class, 'notify']);
    Route::post('cron/reconcile-payments', [InternalTaskController::class, 'reconcilePayments']);
    Route::post('cron/purge', [InternalTaskController::class, 'purge']);
});
