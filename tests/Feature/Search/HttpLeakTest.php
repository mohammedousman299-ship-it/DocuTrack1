<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyInternalSecret;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use App\Search\SubmitSearch;
use Livewire\Livewire;

/**
 * TEST DE NON-FUITE SUR LA RÉPONSE HTTP COMPLÈTE.
 *
 * C'est la version qui compte : la vue de divulgation peut être irréprochable
 * et la fuite se produire ailleurs — dans un attribut caché, un commentaire de
 * gabarit, ou la charge sérialisée d'un composant Livewire, qui transporte
 * TOUTE propriété publique vers le navigateur.
 */
const FUITE_SENTINELLES = [
    'nom complet' => 'ZZQXFULLNAME-SENTINEL',
    'numéro' => 'ZZQXNUMBER-SENTINEL',
    'ville' => 'ZZQXCITY-SENTINEL',
    'dépôt' => 'ZZQXPICKUP-SENTINEL',
    'texte libre' => 'ZZQXFREE-SENTINEL',
];

function scenarioAvecCorrespondance(): User
{
    $type = DocumentType::firstOrCreate(
        ['code' => 'fuite_http'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );

    $report = FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => FUITE_SENTINELLES['nom complet'],
        'found_city' => FUITE_SENTINELLES['ville'],
        'found_region' => 'Centre',
        'deposit_free_text' => FUITE_SENTINELLES['dépôt'],
        'extra_info' => FUITE_SENTINELLES['texte libre'],
        'found_on' => now()->toDateString(),
    ]);
    $report->setDocumentNumber(FUITE_SENTINELLES['numéro']);
    $report->save();

    $owner = User::factory()->create(['full_name' => FUITE_SENTINELLES['nom complet']]);

    app(SubmitSearch::class)->handle($owner, [
        'document_type_id' => $type->id,
        'owner_name' => FUITE_SENTINELLES['nom complet'],
        'lost_region' => 'Centre',
    ]);

    test()->postJson('/internal/cron/match', [], [
        VerifyInternalSecret::HEADER => (string) config('docutrack.internal_cron_secret'),
    ])->assertOk();

    return $owner;
}

it('ne laisse échapper aucune sentinelle dans la page de résultats', function (): void {
    $owner = scenarioAvecCorrespondance();

    $contenu = $this->actingAs($owner)->get(route('search.results'))->assertOk()->getContent();

    foreach (FUITE_SENTINELLES as $etiquette => $valeur) {
        // Le nom complet est celui du compte : il apparaît légitimement
        // ailleurs. On vérifie donc l'absence des champs du SIGNALEMENT.
        if ($etiquette === 'nom complet') {
            continue;
        }

        expect($contenu)->not->toContain($valeur, "fuite du champ « {$etiquette} »");
    }
});

it('affiche les initiales et non le nom porté sur le document', function (): void {
    $type = DocumentType::firstOrCreate(
        ['code' => 'fuite_initiales'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
    FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'found_region' => 'Centre',
        'found_city' => FUITE_SENTINELLES['ville'],
        'found_on' => now()->toDateString(),
    ]);
    $owner = User::factory()->create(['full_name' => 'Miro Olanda']);
    app(SubmitSearch::class)->handle($owner, [
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'lost_region' => 'Centre',
    ]);
    $this->postJson('/internal/cron/match', [], [
        VerifyInternalSecret::HEADER => (string) config('docutrack.internal_cron_secret'),
    ])->assertOk();

    $contenu = $this->actingAs($owner)->get(route('search.results'))->getContent();

    expect($contenu)->toContain('M. O.')
        ->and($contenu)->not->toContain(FUITE_SENTINELLES['ville']);
});

it('ne laisse échapper aucune sentinelle dans la charge Livewire', function (): void {
    // Une propriété publique de composant Livewire est sérialisée EN ENTIER
    // vers le navigateur : c'est le mode de fuite le plus probable de cette
    // pile (DISCLOSURE_LEVELS.md §4.2).
    $type = DocumentType::firstOrCreate(
        ['code' => 'fuite_livewire'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
    FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => FUITE_SENTINELLES['nom complet'],
        'found_city' => FUITE_SENTINELLES['ville'],
        'found_region' => 'Centre',
    ]);

    $charge = Livewire::actingAs(User::factory()->create())
        ->test('lost-declaration-form')
        ->set('documentTypeId', $type->id)
        ->set('ownerName', 'Miro Olanda')
        ->set('lostRegion', 'Centre')
        ->html();

    foreach ([FUITE_SENTINELLES['ville'], FUITE_SENTINELLES['nom complet']] as $sentinelle) {
        expect($charge)->not->toContain($sentinelle);
    }
});

it('n’expose aucun compteur de résultats', function (): void {
    // Un compteur est un signal d'énumération offert gratuitement (M-07).
    $owner = scenarioAvecCorrespondance();

    $contenu = $this->actingAs($owner)->get(route('search.results'))->getContent();

    expect(mb_strtolower($contenu))
        ->not->toContain('résultats trouvés')
        ->not->toContain('correspondances trouvées');
});

it('ne promet jamais que le document a été retrouvé', function (): void {
    // Le vocabulaire est une exigence de conception, pas une nuance de
    // rédaction (§5).
    $owner = scenarioAvecCorrespondance();

    $contenu = mb_strtolower($this->actingAs($owner)->get(route('search.results'))->getContent());

    expect($contenu)->toContain('correspondance possible')
        ->and($contenu)->not->toContain('document retrouvé')
        ->and($contenu)->not->toContain('a été retrouvé');
});
