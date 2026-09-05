<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

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
