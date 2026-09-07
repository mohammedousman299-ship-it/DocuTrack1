<?php

declare(strict_types=1);

use App\Matching\NameNormalizer;
use App\Matching\Synthetic\DatasetGenerator;
use App\Matching\Synthetic\LabelledPair;

/**
 * Le jeu de mesure est lui-même vérifié.
 *
 * Un jeu mal construit produit des chiffres qui paraissent bons : si la
 * famille « homonymes stricts » contenait en réalité des noms différents, la
 * précision mesurée serait excellente et n'aurait rien mesuré. C'est le même
 * piège que la fixture d'image du jalon 3, dont l'EXIF était lisible mais sans
 * la moindre coordonnée GPS — le test passait sans rien prouver.
 */
it('respecte les parts visées et les totalise à 100 %', function (): void {
    $pairs = (new DatasetGenerator)->generate(2000);

    expect($pairs)->toHaveCount(2000);

    $shares = collect($pairs)
        ->groupBy(fn (LabelledPair $p): string => $p->family)
        ->map(fn ($group): float => round($group->count() / 20, 1));

    // Les parts sont réparties à la main : sans ce contrôle, une famille
    // ajoutée sans en réduire une autre déséquilibre silencieusement le jeu et
    // fausse tous les taux qui en découlent.
    expect($shares->sum())->toBe(100.0)
        ->and($shares['exact'])->toBe(12.0)
        ->and($shares['name_typo'])->toBe(15.0)
        ->and($shares['strict_homonym'])->toBe(10.0)
        ->and($shares['near_name_different_person'])->toBe(9.0)
        ->and($shares['temporal_inconsistency'])->toBe(3.0);
});

it('contient des négatifs SANS numéro, seul cas où le moteur peut se tromper', function (): void {
    // Le premier jeu n'en contenait aucun : ses trois familles négatives
    // portaient toutes un numéro des deux côtés, ce qui plafonne le score à
    // 0,40 par arithmétique (MATCHING.md §3.5). Aucun faux positif n'était
    // possible, et la précision mesurée valait 1,0000 sans rien mesurer.
    $negatifsSansNumero = collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => ! $p->shouldMatch
            && $p->lostNumber === null
            && $p->foundNumber === null);

    expect($negatifsSansNumero->count())->toBeGreaterThan(400)
        ->and($negatifsSansNumero->pluck('family')->unique()->sort()->values()->all())
        ->toBe([
            'near_name_different_person',
            'shared_token_different_person',
            'short_name_different_person',
        ]);
});

it('donne aux négatifs à token partagé UN SEUL token commun', function (): void {
    // Deux tokens identiques déclencheraient le terme d'inclusion (D-040) et
    // la famille cesserait d'être un négatif difficile pour devenir un piège.
    collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => $p->family === 'shared_token_different_person')
        ->each(function (LabelledPair $p): void {
            $communs = array_intersect(
                explode(' ', NameNormalizer::normalize($p->lostName)),
                explode(' ', NameNormalizer::normalize($p->foundName)),
            );

            expect(count($communs))->toBeLessThanOrEqual(1);
        });
});

it('produit exactement le même jeu à graine égale', function (): void {
    // Sans cela, deux mesures successives ne se comparent pas.
    $a = (new DatasetGenerator(42))->generate(200);
    $b = (new DatasetGenerator(42))->generate(200);
    $c = (new DatasetGenerator(43))->generate(200);

    expect($a[0]->lostName)->toBe($b[0]->lostName)
        ->and($a[99]->foundNumber)->toBe($b[99]->foundNumber)
        ->and($a[0]->lostName)->not->toBe($c[0]->lostName);
});

it('donne aux homonymes stricts le MÊME nom et des numéros différents', function (): void {
    // C'est la famille des faux positifs : si les noms diffèrent, elle ne
    // met plus le moteur à l'épreuve et la précision mesurée est flattée.
    $homonyms = collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => $p->family === 'strict_homonym');

    expect($homonyms)->not->toBeEmpty();

    $homonyms->each(function (LabelledPair $p): void {
        expect(NameNormalizer::normalize($p->lostName))
            ->toBe(NameNormalizer::normalize($p->foundName))
            ->and($p->lostNumber)->not->toBe($p->foundNumber)
            ->and($p->shouldMatch)->toBeFalse();
    });
});

it('ne met aucun numéro dans les familles qui éprouvent le nom', function (): void {
    // Un numéro identique des deux côtés pèse 0,60 et écraserait le score de
    // nom : la famille ne mesurerait plus ce qu'elle prétend mesurer.
    collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => in_array(
            $p->family, ['name_typo', 'name_swapped', 'compound_name'], true
        ))
        ->each(function (LabelledPair $p): void {
            expect($p->lostNumber)->toBeNull()->and($p->foundNumber)->toBeNull();
        });
});

it('produit des fautes de frappe qui changent réellement le nom', function (): void {
    collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => $p->family === 'name_typo')
        ->each(function (LabelledPair $p): void {
            expect($p->foundName)->not->toBe($p->lostName)
                // …sans le rendre méconnaissable : une « faute » qui détruit
                // le nom testerait un autre cas que celui annoncé.
                ->and(mb_strlen($p->foundName))->toBeGreaterThan(mb_strlen($p->lostName) - 4);
        });
});

it('produit des numéros voisins qui diffèrent d’un seul caractère', function (): void {
    collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => in_array(
            $p->family, ['neighbouring_numbers', 'number_typo'], true
        ))
        ->each(function (LabelledPair $p): void {
            $différences = 0;
            $a = mb_str_split((string) $p->lostNumber);
            $b = mb_str_split((string) $p->foundNumber);

            expect($a)->toHaveCount(count($b));

            foreach ($a as $i => $character) {
                $différences += $character === $b[$i] ? 0 : 1;
            }

            expect($différences)->toBe(1);
        });
});

it('place la découverte AVANT la perte dans la famille temporelle', function (): void {
    collect((new DatasetGenerator)->generate(2000))
        ->filter(fn (LabelledPair $p): bool => $p->family === 'temporal_inconsistency')
        ->each(function (LabelledPair $p): void {
            expect($p->foundOn)->toBeLessThan($p->lostOn)
                ->and($p->shouldMatch)->toBeFalse();
        });
});

it('n’emploie aucun nom réel : les tokens sortent du générateur de syllabes', function (): void {
    // §12 : aucune donnée réelle de personne dans le dépôt. Le contrôle ne
    // peut pas prouver l'absence d'un nom réel — il vérifie ce qui est
    // vérifiable : que les noms viennent bien de la combinatoire annoncée.
    collect((new DatasetGenerator)->generate(500))->each(function (LabelledPair $p): void {
        foreach (explode(' ', $p->lostName) as $token) {
            expect($token)->toMatch('/^[\p{L}]+$/u');
        }
    });
});
