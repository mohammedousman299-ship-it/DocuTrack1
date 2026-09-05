<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PhoneVerificationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
 | Vérification du numéro de téléphone — contrôle anti-Sybil principal (D-013).
 |
 | L'envoi et la saisie ont des limiteurs distincts : l'un borne le coût réel
 | des SMS, l'autre borne les tentatives de devinette d'un code à 6 chiffres.
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/verification-telephone', [PhoneVerificationController::class, 'show'])
        ->name('phone.verify.show');

    Route::post('/verification-telephone/envoi', [PhoneVerificationController::class, 'send'])
        ->middleware('throttle:phone-verification-send')
        ->name('phone.verify.send');

    Route::post('/verification-telephone', [PhoneVerificationController::class, 'verify'])
        ->middleware('throttle:phone-verification-attempt')
        ->name('phone.verify');
});

/*
 | Galerie de composants — HORS PRODUCTION UNIQUEMENT (§9.2).
 |
 | Elle expose la bibliothèque d'interface dans tous ses états. Elle ne
 | contient aucune donnée réelle, mais elle n'a rien à faire en production :
 | la restriction est ici, pas dans une convention.
 */
Route::get('/dev/ui', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return view('dev.ui');
})->name('dev.ui');
