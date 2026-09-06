<?php

declare(strict_types=1);

use App\Enums\LostDeclarationStatus;
use App\Http\Middleware\VerifyInternalSecret;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use App\Search\SubmitSearch;
use Illuminate\Support\Facades\DB;

function typeRecherche(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'recherche'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function criteresValides(DocumentType $type, array $extra = []): array
{
    return array_merge([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'lost_region' => 'Centre',
    ], $extra);
}

function traiterLesRecherches(): void
{
    test()->postJson('/internal/cron/match', [], [
        VerifyInternalSecret::HEADER => (string) config('docutrack.internal_cron_secret'),
    ])->assertOk();
}

it('une seule saisie produit une déclaration ET une demande de recherche', function (): void {
    // D-035 : l'utilisateur perçoit une recherche, le système enregistre aussi
    // une déclaration conservée pour les signalements futurs.
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);

    $result = app(SubmitSearch::class)->handle($owner, criteresValides(typeRecherche()));

    expect($result['declaration']->status)->toBe(LostDeclarationStatus::Active)
        ->and(DB::table('search_requests')->count())->toBe(1);
});

it('refuse une combinaison de critères insuffisante', function (): void {
    $owner = User::factory()->create();

    expect(fn () => app(SubmitSearch::class)->handle($owner, [
        'document_type_id' => typeRecherche()->id,
        'owner_name' => 'Miro Olanda',
    ]))->toThrow(InvalidArgumentException::class);
});

it('met en revue une déclaration au nom d’un tiers', function (): void {
    // M-02, D-036 : le contrôle s'applique sur le seul chemin menant au N1.
    $owner = User::factory()->create(['full_name' => 'Tavi Ndzomo']);

    $result = app(SubmitSearch::class)->handle($owner, criteresValides(typeRecherche()));

    expect($result['declaration']->status)->toBe(LostDeclarationStatus::PendingReview)
        ->and($result['declaration']->allowsAutomaticNotification())->toBeFalse();
});

it('ne rend aucun résultat en synchrone', function (): void {
    // C'est ce report qui supprime le canal temporel et la boucle
    // d'énumération rapide (D-008, M-05, M-07).
    $type = typeRecherche();
    FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
    ]);
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);

    $result = app(SubmitSearch::class)->handle($owner, criteresValides($type));

    expect(array_keys($result))->toBe(['declaration', 'search_request_id'])
        ->and(DB::table('search_results')->count())->toBe(0);
});

it('traite la recherche par le cron et retient les candidats', function (): void {
    $type = typeRecherche();
    FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'found_on' => now()->toDateString(),
    ]);
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);
    app(SubmitSearch::class)->handle($owner, criteresValides($type));

    traiterLesRecherches();

    expect(DB::table('search_results')->count())->toBe(1)
        ->and(DB::table('search_requests')->value('status'))->toBe('processed')
        ->and(DB::table('search_requests')->value('result_count_bucket'))->toBe('one');
});

it('n’enregistre qu’un ordre de grandeur, jamais le compte exact', function (): void {
    // Un compteur exact est un signal d'énumération offert gratuitement (M-07).
    $type = typeRecherche();
    FoundReport::factory()->count(4)->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'found_on' => now()->toDateString(),
    ]);
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);
    app(SubmitSearch::class)->handle($owner, criteresValides($type));

    traiterLesRecherches();

    expect(DB::table('search_requests')->value('result_count_bucket'))->toBe('several');
});

it('envoie le MÊME message qu’il y ait un résultat ou non', function (): void {
    // D-034 : deux messages distincts révéleraient le résultat sur un écran
    // verrouillé et rendraient le signal binaire à un attaquant.
    $type = typeRecherche();
    $avecResultat = User::factory()->create(['full_name' => 'Miro Olanda']);
    FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'found_on' => now()->toDateString(),
    ]);
    app(SubmitSearch::class)->handle($avecResultat, criteresValides($type));

    $sansResultat = User::factory()->create(['full_name' => 'Tavi Bekundi']);
    app(SubmitSearch::class)->handle($sansResultat, criteresValides($type, [
        'owner_name' => 'Tavi Bekundi',
    ]));

    traiterLesRecherches();

    $gabarits = DB::table('notifications')->pluck('template')->unique()->values()->all();

    expect($gabarits)->toBe(['search_completed']);
});

it('est idempotent : rejouer le cron ne notifie pas deux fois', function (): void {
    $type = typeRecherche();
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);
    app(SubmitSearch::class)->handle($owner, criteresValides($type));

    traiterLesRecherches();
    traiterLesRecherches();

    expect(DB::table('notifications')->count())->toBe(1);
});

it('journalise chaque recherche avec son auteur et sa combinaison', function (): void {
    // Exigence explicite du §4.2.
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);
    app(SubmitSearch::class)->handle($owner, criteresValides(typeRecherche()));

    $ligne = DB::table('search_requests')->first();

    expect($ligne->user_id)->toBe($owner->id)
        ->and($ligne->criteria_combination)->toBe('C2');
});

it('ne rapproche jamais deux types de documents différents', function (): void {
    $autre = DocumentType::firstOrCreate(
        ['code' => 'recherche_autre'],
        ['label_fr' => 'Autre', 'label_en' => 'Other', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
    FoundReport::factory()->create([
        'document_type_id' => $autre->id,
        'owner_name' => 'Miro Olanda',
        'found_on' => now()->toDateString(),
    ]);
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);
    app(SubmitSearch::class)->handle($owner, criteresValides(typeRecherche()));

    traiterLesRecherches();

    expect(DB::table('search_results')->count())->toBe(0);
});
