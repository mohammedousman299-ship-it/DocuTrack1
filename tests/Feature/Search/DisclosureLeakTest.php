<?php

declare(strict_types=1);

use App\Disclosure\FoundReportLevel1View;
use App\Models\DocumentType;
use App\Models\FoundReport;

/**
 * TEST DE NON-FUITE (docs/DISCLOSURE_LEVELS.md §5).
 *
 * Il ne vérifie pas ce qui s'affiche : il vérifie CE QUI NE SORT PAS.
 *
 * Les valeurs sensibles du fixture sont des sentinelles uniques et
 * improbables. Toute apparition d'une sentinelle dans une sortie destinée au
 * client est une fuite, qu'elle soit rendue à l'écran, cachée dans un
 * attribut, ou sérialisée dans une charge Livewire.
 */
const SENTINELLES = [
    'nom' => 'ZZQXNAME-SENTINEL',
    'numero' => 'ZZQXNUMBER-SENTINEL',
    'ville' => 'ZZQXCITY-SENTINEL',
    'depot' => 'ZZQXPICKUP-SENTINEL',
    'libre' => 'ZZQXFREE-SENTINEL',
];

function signalementSentinelle(): FoundReport
{
    $type = DocumentType::firstOrCreate(
        ['code' => 'sentinelle'],
        ['label_fr' => 'Type sentinelle', 'label_en' => 'Sentinel type',
            'sensitivity' => 'standard', 'retention_days' => 180]
    );

    $report = FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => SENTINELLES['nom'],
        'found_city' => SENTINELLES['ville'],
        'found_region' => 'Centre',
        'deposit_free_text' => SENTINELLES['depot'],
        'extra_info' => SENTINELLES['libre'],
    ]);

    $report->setDocumentNumber(SENTINELLES['numero']);
    $report->save();

    return $report->fresh();
}

it('ne laisse échapper aucune sentinelle dans une vue de niveau N1', function (): void {
    $vue = FoundReportLevel1View::from(signalementSentinelle());

    $serialise = json_encode($vue, JSON_THROW_ON_ERROR);

    foreach (SENTINELLES as $etiquette => $valeur) {
        expect($serialise)->not->toContain($valeur, "fuite du champ « {$etiquette} » en N1");
    }
});

it('n’expose pas l’identifiant technique du signalement', function (): void {
    // Même non séquentiel, le publier permettrait de recouper deux résultats.
    $report = signalementSentinelle();
    $vue = FoundReportLevel1View::from($report);

    expect(json_encode($vue, JSON_THROW_ON_ERROR))->not->toContain($report->id);
});

it('ne porte que les champs autorisés au niveau N1', function (): void {
    // La classe ne DOIT PAS porter les champs interdits : c'est ce qui rend la
    // fuite impossible plutôt qu'improbable.
    $champs = array_keys(get_object_vars(FoundReportLevel1View::from(signalementSentinelle())));

    sort($champs);

    expect($champs)->toBe([
        'documentTypeLabel', 'foundMonth', 'foundRegion', 'ownerInitials', 'token',
    ]);
});

it('réduit le nom à ses initiales, côté serveur', function (): void {
    $report = FoundReport::factory()->create(['owner_name' => 'Miro Olanda']);

    expect(FoundReportLevel1View::from($report)->ownerInitials)->toBe('M. O.');
});

it('tronque la date de découverte au mois', function (): void {
    $report = FoundReport::factory()->create(['found_on' => '2026-08-14']);

    $mois = FoundReportLevel1View::from($report)->foundMonth;

    expect($mois)->not->toContain('14')
        ->and(mb_strtolower($mois))->toContain('2026');
});

it('n’expose jamais la ville, seulement la région', function (): void {
    $report = FoundReport::factory()->create([
        'found_region' => 'Littoral',
        'found_city' => SENTINELLES['ville'],
    ]);

    $vue = FoundReportLevel1View::from($report);

    expect($vue->foundRegion)->toBe('Littoral')
        ->and(json_encode($vue, JSON_THROW_ON_ERROR))->not->toContain(SENTINELLES['ville']);
});

it('produit un jeton différent pour deux signalements', function (): void {
    $a = FoundReportLevel1View::from(FoundReport::factory()->create());
    $b = FoundReportLevel1View::from(FoundReport::factory()->create());

    expect($a->token)->not->toBe($b->token)
        ->and(mb_strlen($a->token))->toBe(32);
});

it('reste stable pour un même signalement', function (): void {
    // Un jeton qui changerait à chaque rendu empêcherait l'utilisateur de
    // reprendre une correspondance consultée plus tôt.
    $report = FoundReport::factory()->create();

    expect(FoundReportLevel1View::from($report)->token)
        ->toBe(FoundReportLevel1View::from($report->fresh())->token);
});

it('n’expose ni score, ni version d’algorithme, ni compteur', function (): void {
    // Le score est un oracle de matching : il renseignerait un attaquant sur
    // la qualité de son approximation et lui permettrait de converger.
    $serialise = json_encode(FoundReportLevel1View::from(signalementSentinelle()), JSON_THROW_ON_ERROR);

    foreach (['score', 'algorithm', 'count', 'total', 'similarity'] as $interdit) {
        expect(mb_strtolower($serialise))->not->toContain($interdit);
    }
});
