<?php

declare(strict_types=1);

use App\Enums\AdminRole;
use App\Internal\TaskBudget;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use App\Search\ProcessSearchRequests;
use App\Search\SubmitSearch;
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

/*
 |------------------------------------------------------------------------
 | Pages du jalon 4
 |------------------------------------------------------------------------
 |
 | La page de résultats est celle qui risque le plus la dérive : elle boucle
 | sur les recherches d'un compte, et chaque tour peut coûter des requêtes.
 | Le test la charge avec PLUSIEURS recherches, faute de quoi il passerait
 | tout en laissant un N+1 intact.
 */

it('respecte le budget sur le formulaire de recherche', function (): void {
    $user = User::factory()->create();

    DocumentType::firstOrCreate(
        ['code' => 'budget'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );

    $count = comptezLesRequetes(fn () => $this->actingAs($user)->get('/recherche')->assertOk());

    expect($count)->toBeLessThanOrEqual(BUDGET);
});

it('respecte le budget sur les résultats, quel que soit le nombre de recherches', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    $type = DocumentType::firstOrCreate(
        ['code' => 'budget'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );

    config(['docutrack.limits.searches_per_day' => 50]);

    for ($i = 0; $i < 6; $i++) {
        FoundReport::factory()->create([
            'document_type_id' => $type->id,
            'owner_name' => 'Miro Olanda',
            'status' => 'active',
        ]);

        app(SubmitSearch::class)->handle($user, [
            'document_type_id' => $type->id,
            'owner_name' => 'Miro Olanda',
            'lost_region' => 'Centre',
        ]);
    }

    app(ProcessSearchRequests::class)->handle(new TaskBudget(10));

    $count = comptezLesRequetes(fn () => $this->actingAs($user)->get('/recherche/resultats')->assertOk());

    expect($count)->toBeLessThanOrEqual(BUDGET);
});

it('respecte le budget sur la file de revue', function (): void {
    $reviewer = User::factory()->admin(AdminRole::Sensitive)->create();

    $type = DocumentType::firstOrCreate(
        ['code' => 'budget'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );

    for ($i = 0; $i < 6; $i++) {
        $user = User::factory()->create(['full_name' => 'Miro Olanda '.$i]);

        app(SubmitSearch::class)->handle($user, [
            'document_type_id' => $type->id,
            'owner_name' => 'Yolena Bassim',
            'lost_region' => 'Centre',
        ]);
    }

    $count = comptezLesRequetes(
        fn () => $this->actingAs($reviewer)->get('/administration/revue-declarations')->assertOk()
    );

    expect($count)->toBeLessThanOrEqual(BUDGET);
});
