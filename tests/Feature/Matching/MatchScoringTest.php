<?php

declare(strict_types=1);

use App\Enums\LostDeclarationStatus;
use App\Enums\NameConsistency;
use App\Matching\MatchScorer;
use App\Matching\NameScore;
use App\Matching\RecordMatches;
use App\Matching\TemporalFactor;
use App\Models\DocumentMatch;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\LostDeclaration;
use App\Models\MatchingSetting;
use App\Models\User;
use App\Search\SearchMatcher;
use Illuminate\Support\Carbon;

function reglages(): MatchingSetting
{
    return MatchingSetting::active();
}

function typeScore(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'score'],
        ['label_fr' => 'Carte', 'label_en' => 'Card', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

/*
 |------------------------------------------------------------------------
 | Score de nom
 |------------------------------------------------------------------------
 */

it('rattrape la faute d’un caractère, que le trigramme seul sous-évalue', function (): void {
    // Mesuré au jalon 1 : le trigramme seul donne 0,667 sur ce cas, soit sous
    // le seuil. C'est la raison d'être du terme de distance d'édition (D-018).
    [$score] = NameScore::forPairs([['ndoube tsogin', 'ndoube tsogim']]);

    expect($score)->toBeGreaterThan(0.90);
});

it('rattrape le prénom omis, que les deux premiers termes punissent', function (): void {
    // D-040 : sans le terme d'inclusion, ce cas obtenait 0,63 — moins qu'une
    // personne différente au nom proche.
    [$avec] = NameScore::forPairs([['bela ndoube tsogin', 'bela tsogin']]);
    [$sans] = NameScore::forPairs([['bela ndoube tsogin', 'bela tsogin']], withContainment: false);

    expect($avec)->toBe(1.0)
        ->and($sans)->toBeLessThan(0.75);
});

it('ne récompense PAS un seul token partagé', function (): void {
    // Le patronyme commun est le cas banal de deux personnes différentes.
    [$score] = NameScore::forPairs([['ndoube tsogin', 'ndoube kwalar']]);

    expect($score)->toBeLessThan(0.55);
});

it('écarte la distance d’édition sur les noms courts', function (): void {
    // Sur 4 caractères, une lettre d'écart donne 0,75 en distance normalisée.
    [$avecPlancher] = NameScore::forPairs([['bela', 'bola']]);
    [$sansPlancher] = NameScore::forPairs([['bela', 'bola']], minimumLength: 0);

    expect($avecPlancher)->toBeLessThan(0.55)
        ->and($sansPlancher)->toBeGreaterThan(0.70);
});

/*
 |------------------------------------------------------------------------
 | Facteur temporel
 |------------------------------------------------------------------------
 */

it('annule une découverte antérieure à la perte, sans la décoter', function (): void {
    // Multiplicatif et non additif : un couple physiquement impossible ne doit
    // pas pouvoir franchir le seuil grâce à un excellent score de nom.
    $perte = Carbon::create(2026, 6, 1);

    expect(TemporalFactor::for($perte, $perte->copy()->subDays(30)))->toBe(0.0)
        // La tolérance couvre une date de perte approximative, qu'on constate
        // souvent plusieurs jours après les faits.
        ->and(TemporalFactor::for($perte, $perte->copy()->subDays(5)))->toBe(1.0);
});

it('traite une date absente comme neutre, jamais comme un défaut', function (): void {
    expect(TemporalFactor::for(null, Carbon::now()))->toBe(1.0)
        ->and(TemporalFactor::for(Carbon::now(), null))->toBe(1.0);
});

/*
 |------------------------------------------------------------------------
 | Renormalisation
 |------------------------------------------------------------------------
 */

it('ne pénalise pas un couple sans numéro des deux côtés', function (): void {
    // Sans renormalisation, il plafonnerait à 0,40 et ne serait jamais
    // notifié — alors que l'absence de numéro est courante et légitime.
    $score = (new MatchScorer(reglages()))->score(
        lostNumberHmac: null, foundNumberHmac: null,
        nameScore: 1.0,
        lostRegion: 'Centre', foundRegion: 'Centre',
        lostOn: null, foundOn: null,
    );

    expect($score->score)->toBe(1.0)
        ->and($score->breakdown['components'])->not->toHaveKey('number');
});

it('plafonne à 0,40 quand deux numéros diffèrent', function (): void {
    // MATCHING.md §3.5 : deux numéros différents désignent deux documents
    // différents. C'est correct en général, et c'est aussi ce qui fait
    // disparaître une correspondance valide sur une simple faute de frappe.
    $score = (new MatchScorer(reglages()))->score(
        lostNumberHmac: 'aaa', foundNumberHmac: 'bbb',
        nameScore: 1.0,
        lostRegion: 'Centre', foundRegion: 'Centre',
        lostOn: null, foundOn: null,
    );

    expect($score->score)->toBe(0.4)
        ->and($score->possibleNumberTypo)->toBeTrue();
});

it('ne lève pas le drapeau de faute de frappe sur un nom éloigné', function (): void {
    $score = (new MatchScorer(reglages()))->score(
        lostNumberHmac: 'aaa', foundNumberHmac: 'bbb',
        nameScore: 0.40,
        lostRegion: 'Centre', foundRegion: 'Centre',
        lostOn: null, foundOn: null,
    );

    expect($score->possibleNumberTypo)->toBeFalse();
});

it('conserve le détail du calcul, sans quoi aucun score n’est explicable', function (): void {
    $score = (new MatchScorer(reglages()))->score(
        lostNumberHmac: 'aaa', foundNumberHmac: 'aaa',
        nameScore: 0.80,
        lostRegion: 'Centre', foundRegion: 'Littoral',
        lostOn: Carbon::create(2026, 1, 1), foundOn: Carbon::create(2027, 6, 1),
    );

    expect($score->breakdown)->toHaveKeys(['components', 'weight_sum', 'base', 'temporal_factor'])
        ->and($score->breakdown['temporal_factor'])->toBe(0.8)
        ->and($score->breakdown['components']['geography']['value'])->toBe(0.0)
        ->and($score->breakdown['algorithm_version'])->toBe(reglages()->algorithm_version);
});

/*
 |------------------------------------------------------------------------
 | Enregistrement
 |------------------------------------------------------------------------
 */

/**
 * Déclaration et signalement identiques : le cas qui doit toujours aboutir.
 *
 * @return array{0: LostDeclaration, 1: FoundReport}
 */
function couplePlausible(?string $nomSignalement = null): array
{
    $type = typeScore();
    $report = FoundReport::factory()->create([
        'document_type_id' => $type->id,
        'owner_name' => $nomSignalement ?? 'Miro Olanda',
        'found_region' => 'Centre',
        'found_on' => now()->toDateString(),
    ]);

    $declaration = new LostDeclaration([
        'user_id' => User::factory()->create(['full_name' => 'Miro Olanda'])->id,
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'lost_region' => 'Centre',
    ]);
    $declaration->status = LostDeclarationStatus::Active;
    $declaration->name_consistency = NameConsistency::Match;
    $declaration->expires_at = now()->addDays(180);
    $declaration->save();

    return [$declaration, $report];
}

it('enregistre une correspondance franche et la marque notifiable', function (): void {
    // Ce test aurait attrapé le scoreur muet : le conteneur injectait un
    // MatchingSetting VIDE, tous poids à zéro, et plus aucune correspondance
    // n'était enregistrée — sans la moindre erreur.
    [$declaration, $report] = couplePlausible();

    $matches = app(RecordMatches::class)->handle(
        $declaration,
        app(SearchMatcher::class)->candidatesFor($declaration),
        reglages(),
    );

    expect($matches)->toHaveCount(1)
        ->and((float) $matches->first()->score)->toBe(1.0)
        ->and($matches->first()->status)->toBe('candidate')
        ->and($matches->first()->algorithm_version)->toBe(reglages()->algorithm_version);
});

it('n’écrit RIEN sous le seuil de revue', function (): void {
    // Écrire les couples faibles remplirait la table de bruit et surtout
    // constituerait une trace de « qui a failli correspondre à quoi » — une
    // donnée que personne n'a demandé à produire.
    [$declaration, $report] = couplePlausible('Kwalar Mbenda Ngoya');

    app(RecordMatches::class)->handle(
        $declaration,
        collect([$report]),
        reglages(),
    );

    expect(DocumentMatch::count())->toBe(0);
});

it('est idempotent : rejouer ne crée ni doublon ni seconde notification', function (): void {
    [$declaration] = couplePlausible();
    $candidates = app(SearchMatcher::class)->candidatesFor($declaration);

    app(RecordMatches::class)->handle($declaration, $candidates, reglages());
    app(RecordMatches::class)->handle($declaration, $candidates, reglages());

    expect(DocumentMatch::count())->toBe(1);
});

it('refuse de tourner sans réglages actifs, plutôt que sur des valeurs implicites', function (): void {
    // Retomber sur des valeurs par défaut ferait tourner le moteur avec des
    // seuils que personne n'a choisis, et rien ne le signalerait.
    MatchingSetting::query()->update(['is_active' => false]);

    expect(fn () => MatchingSetting::active())->toThrow(RuntimeException::class);
});
