<?php

declare(strict_types=1);

use App\Documents\DocumentNumber;
use App\Matching\NameNormalizer;
use App\Models\DocumentType;
use App\Models\User;
use App\Search\Abuse\ApplyEnumerationResponse;
use App\Search\Abuse\EnumerationDetector;
use App\Search\SubmitSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function typeEnum(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'enumeration'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

/** @param array<string, mixed> $extra */
function rechercher(User $user, array $extra): void
{
    app(SubmitSearch::class)->handle($user, array_merge([
        'document_type_id' => typeEnum()->id,
        'owner_name' => $user->full_name,
        'lost_region' => 'Centre',
    ], $extra));
}

it('laisse passer une activité normale', function (): void {
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    rechercher($user, []);
    rechercher($user, []);

    expect($user->fresh()->isBlocked())->toBeFalse();
});

it('détecte le balayage de numéros', function (): void {
    // Volume de numéros DISTINCTS, faute de pouvoir mesurer leur proximité :
    // le HMAC détruit toute similarité entre entrées voisines (D-007).
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    foreach (['AB100001', 'AB100002', 'AB100003', 'AB100004'] as $numero) {
        if ($user->fresh()->isBlocked()) {
            break;
        }
        rechercher($user, ['document_number' => $numero]);
    }

    $signaux = app(EnumerationDetector::class)->signalsFor($user);

    expect($signaux->distinctNumbers)->toBeGreaterThanOrEqual(3)
        ->and(app(EnumerationDetector::class)->isEnumerating($signaux))->toBeTrue();
});

it('détecte le balayage de noms voisins', function (): void {
    // Sur les noms, la similarité RESTE mesurable : ils sont conservés
    // normalisés en clair pour le rapprochement.
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    foreach (['Miro Olanda', 'Miro Olandra', 'Miro Olandaa'] as $nom) {
        DB::table('search_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'document_type_id' => typeEnum()->id,
            'owner_name_normalized' => NameNormalizer::normalize($nom),
            'criteria_combination' => 'C2',
            'status' => 'processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $signaux = app(EnumerationDetector::class)->signalsFor($user);

    expect($signaux->nameSimilarityMax)->toBeGreaterThan(0.6)
        ->and(app(EnumerationDetector::class)->isEnumerating($signaux))->toBeTrue();
});

it('ne confond pas des noms sans rapport avec un balayage', function (): void {
    // Chercher pour plusieurs proches est légitime : ce cas relève de la
    // revue humaine (D-036), pas du blocage.
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    foreach (['Miro Olanda', 'Tavi Bekundi', 'Ndzomo Ayo'] as $nom) {
        DB::table('search_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'document_type_id' => typeEnum()->id,
            'owner_name_normalized' => NameNormalizer::normalize($nom),
            'criteria_combination' => 'C2',
            'status' => 'processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $signaux = app(EnumerationDetector::class)->signalsFor($user);

    expect(app(EnumerationDetector::class)->isEnumerating($signaux))->toBeFalse();
});

it('bloque de façon graduée et journalise', function (): void {
    // Un utilisateur légitime ne doit pas être traité comme un attaquant dès
    // le premier signal.
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    foreach (['AB100001', 'AB100002', 'AB100003', 'AB100004'] as $numero) {
        DB::table('search_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'document_type_id' => typeEnum()->id,
            'number_hmac' => DocumentNumber::hmacForInput($numero),
            'criteria_combination' => 'C1',
            'status' => 'processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $premier = app(ApplyEnumerationResponse::class)->handle($user);
    expect($premier['blocked'])->toBeTrue();

    $user->forceFill(['blocked_until' => null])->save();
    $second = app(ApplyEnumerationResponse::class)->handle($user->fresh());

    $premiereDuree = now()->diffInMinutes($premier['until']);
    $secondeDuree = now()->diffInMinutes($second['until']);

    expect($secondeDuree)->toBeGreaterThan($premiereDuree)
        ->and(DB::table('audit_logs')->where('action', 'search.enumeration_blocked')->count())->toBe(2);
});

it('ne journalise que les signaux, jamais les critères de recherche', function (): void {
    // Le journal ne doit pas devenir un second entrepôt de données de
    // recherche.
    $user = User::factory()->create(['full_name' => 'Miro Olanda']);

    foreach (['AB100001', 'AB100002', 'AB100003', 'AB100004'] as $numero) {
        DB::table('search_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'document_type_id' => typeEnum()->id,
            'number_hmac' => DocumentNumber::hmacForInput($numero),
            'owner_name_normalized' => 'miro olanda',
            'criteria_combination' => 'C1',
            'status' => 'processed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    app(ApplyEnumerationResponse::class)->handle($user);

    $motif = DB::table('audit_logs')->where('action', 'search.enumeration_blocked')->value('reason');

    expect($motif)->not->toContain('AB100001')
        ->and($motif)->not->toContain('miro olanda')
        ->and($motif)->toContain('numeros_distincts');
});

it('refuse la recherche à un compte bloqué', function (): void {
    $user = User::factory()->create(['blocked_until' => now()->addHour()]);

    expect(fn () => rechercher($user, ['document_number' => 'AB999999']))
        ->toThrow(InvalidArgumentException::class);
});
