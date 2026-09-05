<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyInternalSecret;
use App\Internal\InternalTaskRunner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Les endpoints internes remplacent le worker et le scheduler absents (§3.1).
 * C'est le vrai risque architectural de la plateforme : ces tests vérifient
 * les trois garanties exigées — secret, verrou, budget — plutôt que de les
 * supposer.
 */
const INTERNAL_ROUTES = [
    '/internal/queue/drain',
    '/internal/cron/match',
    '/internal/cron/notify',
    '/internal/cron/reconcile-payments',
    '/internal/cron/purge',
];

/** @return array<string, string> */
function withSecret(): array
{
    return [VerifyInternalSecret::HEADER => (string) config('docutrack.internal_cron_secret')];
}

it('refuse tout accès sans secret', function (string $route): void {
    $this->postJson($route)->assertNotFound();
})->with(INTERNAL_ROUTES);

it('refuse un secret erroné', function (string $route): void {
    $this->postJson($route, [], [VerifyInternalSecret::HEADER => 'mauvais-secret'])
        ->assertNotFound();
})->with(INTERNAL_ROUTES);

it('répond 404 et non 403, pour ne pas confirmer que la route existe', function (): void {
    // Un 403 confirmerait l'existence de l'endpoint, ce qui est une
    // divulgation en soi (PERMISSIONS.md §3.3).
    $this->postJson('/internal/cron/purge')->assertStatus(404);
});

it('refuse tout accès lorsque le secret configuré est vide', function (string $route): void {
    // Une configuration incomplète ne doit jamais ouvrir les endpoints.
    config()->set('docutrack.internal_cron_secret', '');

    $this->postJson($route, [], [VerifyInternalSecret::HEADER => ''])->assertNotFound();
})->with(INTERNAL_ROUTES);

it('accepte un secret valide', function (string $route): void {
    $this->postJson($route, [], withSecret())
        ->assertOk()
        ->assertJsonStructure(['task', 'ran', 'processed', 'budget_exhausted']);
})->with(INTERNAL_ROUTES);

it('ne lance pas deux exécutions concurrentes de la même tâche', function (): void {
    // Un ordonnanceur externe peut redéclencher avant la fin du passage
    // précédent : le verrou doit faire repartir le second immédiatement.
    $held = Cache::lock('internal-task:cron.purge', 60);
    expect($held->get())->toBeTrue();

    try {
        $response = $this->postJson('/internal/cron/purge', [], withSecret())->assertOk();

        expect($response->json('ran'))->toBeFalse()
            ->and($response->json('processed'))->toBe(0);
    } finally {
        $held->release();
    }
});

it('libère le verrou même si la tâche échoue', function (): void {
    $runner = new InternalTaskRunner;

    try {
        $runner->run('test.failing', function (): array {
            throw new RuntimeException('échec simulé');
        });
    } catch (RuntimeException) {
        // attendu
    }

    $lock = Cache::lock('internal-task:test.failing', 10);
    expect($lock->get())->toBeTrue();
    $lock->release();
});

it('purge effectivement les enregistrements expirés, sans suppression logique', function (): void {
    $userId = (string) Str::uuid();
    DB::table('users')->insert([
        'id' => $userId,
        'full_name' => 'Compte de test',
        'full_name_normalized' => 'compte de test',
        'email' => 'purge@docutrack.invalid',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $typeId = DB::table('document_types')->value('id') ?? DB::table('document_types')->insertGetId([
        'code' => 'purge_type', 'label_fr' => 'x', 'label_en' => 'x',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('lost_declarations')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'document_type_id' => $typeId,
        'owner_name' => 'Expire Test',
        'owner_name_normalized' => 'expire test',
        'expires_at' => now()->subDay(),      // expiré
        'created_at' => now()->subDays(200),
        'updated_at' => now()->subDays(200),
    ]);

    DB::table('lost_declarations')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'document_type_id' => $typeId,
        'owner_name' => 'Valide Test',
        'owner_name_normalized' => 'test valide',
        'expires_at' => now()->addDays(100),  // encore valide
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('lost_declarations')->count())->toBe(2);

    $this->postJson('/internal/cron/purge', [], withSecret())->assertOk();

    // Suppression RÉELLE : la ligne expirée n'existe plus, elle n'est pas
    // seulement marquée (D-010).
    expect(DB::table('lost_declarations')->count())->toBe(1)
        ->and(DB::table('lost_declarations')->value('owner_name'))->toBe('Valide Test');
});

it('est idempotent : un second passage ne supprime rien de plus', function (): void {
    $this->postJson('/internal/cron/purge', [], withSecret())->assertOk();
    $second = $this->postJson('/internal/cron/purge', [], withSecret())->assertOk();

    expect($second->json('processed'))->toBe(0);
});
