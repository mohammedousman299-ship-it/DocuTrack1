<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Budget de requêtes SQL (§9.4) : aucune page au-delà de 15 requêtes.
 *
 * Vérifié par test automatisé plutôt que constaté en production : une page qui
 * dérive vers des dizaines de requêtes devient injouable sur un réseau 3G, et
 * la dérive est progressive — elle ne se remarque qu'une fois installée.
 */
/**
 * Force les pilotes de PRODUCTION avant de mesurer.
 *
 * La configuration de test place session et cache en mémoire, ce qui ramène le
 * compte à zéro sur les pages publiques et rendrait la mesure trompeuse : en
 * production, session et cache sont en base (contrainte de plateforme §3.1).
 */
function comptezLesRequetes(callable $action): int
{
    config()->set('session.driver', 'database');
    config()->set('cache.default', 'database');

    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    $action();

    return $count;
}

const BUDGET = 15;

it('respecte le budget sur la page d’accueil', function (): void {
    $count = comptezLesRequetes(fn () => $this->get('/')->assertOk());

    expect($count)->toBeLessThanOrEqual(BUDGET);
});

it('respecte le budget sur l’inscription', function (): void {
    $count = comptezLesRequetes(fn () => $this->get('/register')->assertOk());

    expect($count)->toBeLessThanOrEqual(BUDGET);
});

it('respecte le budget sur la connexion', function (): void {
    $count = comptezLesRequetes(fn () => $this->get('/login')->assertOk());

    expect($count)->toBeLessThanOrEqual(BUDGET);
});

it('respecte le budget sur la vérification du téléphone', function (): void {
    $user = User::factory()->unverifiedPhone()->create();

    $count = comptezLesRequetes(
        fn () => $this->actingAs($user)->get(route('phone.verify.show'))->assertOk()
    );

    expect($count)->toBeLessThanOrEqual(BUDGET);
});

it('respecte le budget sur le tableau de bord d’administration', function (): void {
    $admin = User::factory()->admin()->create();

    $count = comptezLesRequetes(
        fn () => $this->actingAs($admin)->get('/administration')->assertOk()
    );

    expect($count)->toBeLessThanOrEqual(BUDGET);
});
