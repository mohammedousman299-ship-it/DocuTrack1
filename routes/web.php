<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PhoneVerificationController;
use App\Http\Controllers\Reports\UploadTicketController;
use App\Http\Controllers\Search\SearchResultsController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

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

/*
 | Activation de la double authentification.
 |
 | Accessible à tout compte authentifié : elle est obligatoire pour les
 | administrateurs (D-014) et recommandée pour les autres.
 */
Route::middleware('auth')->get('/double-authentification', fn () => view('auth.two-factor-setup'))
    ->name('two-factor.setup');

/*
 | Espace d'administration.
 |
 | Le middleware 'auth' est VOLONTAIREMENT absent : il redirige un visiteur
 | anonyme vers la connexion, et cette redirection confirme que la route
 | existe — exactement la divulgation que la §3.3 de PERMISSIONS.md interdit.
 |
 | 'admin.2fa' traite lui-même le cas anonyme et renvoie 404, de sorte que
 | l'interface d'administration est indiscernable d'une URL inexistante pour
 | qui n'y a pas droit.
 */
Route::middleware(['admin.2fa'])->prefix('administration')->group(function (): void {
    Route::get('/', fn () => view('admin.dashboard'))->name('admin.dashboard');
});

/*
 | Parcours du Trouveur (§1.5).
 |
 | Protégé par 'phone.verified' : sans numéro vérifié, les plafonds par compte
 | seraient décoratifs et l'index de rapprochement deviendrait un canal
 | d'extraction (D-013, M-06).
 */
Route::middleware(['auth', 'phone.verified'])->group(function (): void {
    Route::view('/signalement', 'reports.create')->name('reports.create');

    // Autorisation d'envoi direct : une URL d'écriture dans le stockage n'est
    // pas une ressource anonyme, d'où l'authentification ET la limitation.
    Route::post('/signalement/piece-jointe/ticket', UploadTicketController::class)
        ->middleware('throttle:upload')
        ->name('reports.upload-ticket');
});

/*
 | Parcours du Propriétaire (§1.3, §1.4).
 |
 | Une seule saisie produit une déclaration de perte ET une demande de
 | recherche (D-035). Le quota quotidien porte sur la soumission, pas sur la
 | consultation des résultats.
 */
Route::middleware(['auth', 'phone.verified'])->group(function (): void {
    Route::view('/recherche', 'search.create')->name('search.create');
    Route::get('/recherche/resultats', SearchResultsController::class)->name('search.results');
});
