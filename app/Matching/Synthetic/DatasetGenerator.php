<?php

declare(strict_types=1);

namespace App\Matching\Synthetic;

use Illuminate\Support\Carbon;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Jeu de paires étiquetées pour le calibrage des seuils (MATCHING.md §6.1).
 *
 * DÉTERMINISTE à graine donnée. Une mesure qu'on ne peut pas rejouer sur le
 * même jeu ne se compare à rien — ni à la mesure d'hier, ni à celle qui suivra
 * un changement de formule.
 *
 * ------------------------------------------------------------------------
 * Un choix de conception qui décide de ce que la mesure peut montrer.
 *
 * Les familles censées éprouver le SCORE DE NOM (faute de frappe, inversion,
 * nom composé) sont générées **sans numéro d'aucun côté**. Avec un numéro
 * identique des deux côtés, la composante `N` pèse 0,60 et écrase tout : la
 * paire serait rapprochée quelle que soit la qualité du score de nom, et la
 * famille ne mesurerait rien. C'est une décision, pas une omission.
 * ------------------------------------------------------------------------
 */
final class DatasetGenerator
{
    /**
     * Parts visées. La somme fait 100.
     *
     * ------------------------------------------------------------------------
     * ÉCART ASSUMÉ avec le tableau d'origine de MATCHING.md §6.1, constaté à la
     * première mesure.
     *
     * Le jeu d'origine donnait une précision de 1,0000 à tous les seuils à
     * partir de 0,45. Ce chiffre ne mesurait rien : ses TROIS familles
     * négatives portaient toutes un numéro DES DEUX CÔTÉS. Or deux numéros
     * différents mettent la composante `N` à zéro, et le score plafonne
     * arithmétiquement à 0,40 (MATCHING.md §3.5) — sous tout seuil balayé.
     * Le jeu ne pouvait produire aucun faux positif, et la précision parfaite
     * était une propriété des pondérations, pas du moteur.
     *
     * Deux familles négatives DIFFICILES ont été ajoutées, toutes deux SANS
     * numéro d'aucun côté — le seul cas où le nom porte seul la décision et où
     * le moteur peut réellement se tromper :
     *
     *   - `shared_token_different_person` : deux personnes de même patronyme.
     *     Banal, et le trigramme y voit une forte similarité.
     *   - `near_name_different_person` : noms proches à 2-3 caractères près,
     *     à la frontière de la faute de frappe.
     *
     * La seconde est délibérément voisine de `name_typo` : dans le monde réel
     * ces deux situations sont INDISCERNABLES à partir des seules données.
     * C'est précisément ce qui fait exister un compromis précision/rappel — et
     * un jeu qui l'esquive rend une mesure flatteuse et fausse.
     * ------------------------------------------------------------------------
     */
    private const FAMILIES = [
        'exact' => 12,
        'name_typo' => 15,
        'name_swapped' => 8,
        'compound_name' => 8,
        'strict_homonym' => 10,
        'shared_token_different_person' => 10,
        'near_name_different_person' => 9,
        'short_name_different_person' => 3,
        'number_missing_one_side' => 7,
        'number_missing_both' => 4,
        'neighbouring_numbers' => 4,
        'number_typo' => 5,
        'temporal_inconsistency' => 3,
        'duplicate_report' => 2,
    ];

    /** @var list<string> */
    private const REGIONS = [
        'Adamaoua', 'Centre', 'Est', 'Extrême-Nord', 'Littoral',
        'Nord', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Ouest',
    ];

    private readonly Randomizer $randomizer;

    private readonly SyntheticName $names;

    private readonly SyntheticNumber $numbers;

    public function __construct(int $seed = 20260907)
    {
        $this->randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $this->names = new SyntheticName($this->randomizer);
        $this->numbers = new SyntheticNumber($this->randomizer);
    }

    /** @return list<LabelledPair> */
    public function generate(int $total = 2000): array
    {
        $pairs = [];

        foreach (self::FAMILIES as $family => $share) {
            $count = (int) round($total * $share / 100);

            for ($i = 0; $i < $count; $i++) {
                $pairs[] = $this->buildOne($family);
            }
        }

        return $pairs;
    }

    private function buildOne(string $family): LabelledPair
    {
        $name = $this->names->fullName();
        $number = $this->numbers->generate();
        $region = $this->region();
        $lostOn = Carbon::create(2026, 1, 1)->addDays($this->randomizer->getInt(0, 200));
        $foundOn = $lostOn->copy()->addDays($this->randomizer->getInt(0, 30));

        return match ($family) {
            // Non-régression : tout concorde.
            'exact', 'duplicate_report' => $this->pair(
                $family, true,
                $name, $number, $region, $lostOn,
                $name, $number, $region, $foundOn,
            ),

            // Le nom porte seul la décision : pas de numéro (voir l'en-tête).
            'name_typo' => $this->pair(
                $family, true,
                $name, null, $region, $lostOn,
                $this->names->withTypo($name, $this->randomizer->getInt(1, 2)), null, $region, $foundOn,
            ),

            'name_swapped' => $this->pair(
                $family, true,
                $name, null, $region, $lostOn,
                $this->names->swapped($name), null, $region, $foundOn,
            ),

            // Nom composé : un côté omet un token, cas courant du monde réel.
            'compound_name' => $this->compoundPair($region, $lostOn, $foundOn),

            // FAUX POSITIFS : même nom, documents bel et bien différents.
            'strict_homonym' => $this->pair(
                $family, false,
                $name, $number, $region, $lostOn,
                $name, $this->numbers->generate(), $this->region(), $foundOn,
            ),

            // NÉGATIFS DIFFICILES : sans numéro, le nom porte seul la
            // décision — c'est là, et seulement là, que le moteur peut
            // produire un faux positif.
            'shared_token_different_person' => $this->pair(
                $family, false,
                $name, null, $region, $lostOn,
                $this->names->sharingToken($name), null, $region, $foundOn,
            ),

            // Noms COURTS de personnes différentes : éprouve le plancher de
            // longueur du score de nom. Sur 4 caractères, une lettre d'écart
            // donne 0,75 en distance d'édition — un faux positif offert.
            'short_name_different_person' => $this->shortNamePair($region, $lostOn, $foundOn),

            'near_name_different_person' => $this->pair(
                $family, false,
                $name, null, $region, $lostOn,
                $this->names->withTypo($name, $this->randomizer->getInt(2, 3)), null, $region, $foundOn,
            ),

            // Renormalisation §3.2 : un champ absent doit être NEUTRE.
            'number_missing_one_side' => $this->pair(
                $family, true,
                $name, $number, $region, $lostOn,
                $name, null, $region, $foundOn,
            ),

            'number_missing_both' => $this->pair(
                $family, true,
                $name, null, $region, $lostOn,
                $name, null, $region, $foundOn,
            ),

            // Ne doivent PAS se rapprocher : noms différents, numéros voisins.
            'neighbouring_numbers' => $this->pair(
                $family, false,
                $name, $number, $region, $lostOn,
                $this->names->fullName(), $this->numbers->neighbourOf($number), $this->region(), $foundOn,
            ),

            // Coût réel de D-007 : vraie correspondance, numéro mal saisi.
            'number_typo' => $this->pair(
                $family, true,
                $name, $number, $region, $lostOn,
                $name, $this->numbers->neighbourOf($number), $region, $foundOn,
            ),

            // Facteur T : découverte ANTÉRIEURE à la perte, physiquement
            // impossible — le score doit être annulé, pas décoté.
            'temporal_inconsistency' => $this->pair(
                $family, false,
                $name, $number, $region, $lostOn,
                $name, $number, $region, $lostOn->copy()->subDays($this->randomizer->getInt(30, 300)),
            ),

            default => throw new \InvalidArgumentException("Famille inconnue : {$family}"),
        };
    }

    /** Deux personnes différentes portant un nom court d'un seul token. */
    private function shortNamePair(string $region, Carbon $lostOn, Carbon $foundOn): LabelledPair
    {
        $name = $this->names->shortToken();

        return $this->pair(
            'short_name_different_person', false,
            $name, null, $region, $lostOn,
            $this->names->withTypo($name), null, $region, $foundOn,
        );
    }

    /** Nom de 3 ou 4 tokens dont un côté en omet un. */
    private function compoundPair(string $region, Carbon $lostOn, Carbon $foundOn): LabelledPair
    {
        $tokens = [];

        for ($i = 0; $i < $this->randomizer->getInt(3, 4); $i++) {
            $tokens[] = $this->names->token();
        }

        $shortened = $tokens;
        unset($shortened[$this->randomizer->getInt(1, count($tokens) - 1)]);

        return $this->pair(
            'compound_name', true,
            implode(' ', $tokens), null, $region, $lostOn,
            implode(' ', $shortened), null, $region, $foundOn,
        );
    }

    private function pair(
        string $family,
        bool $shouldMatch,
        string $lostName,
        ?string $lostNumber,
        ?string $lostRegion,
        Carbon $lostOn,
        string $foundName,
        ?string $foundNumber,
        ?string $foundRegion,
        Carbon $foundOn,
    ): LabelledPair {
        return new LabelledPair(
            family: $family,
            shouldMatch: $shouldMatch,
            lostName: $lostName,
            lostNumber: $lostNumber,
            lostRegion: $lostRegion,
            lostOn: $lostOn->toDateString(),
            foundName: $foundName,
            foundNumber: $foundNumber,
            foundRegion: $foundRegion,
            foundOn: $foundOn->toDateString(),
        );
    }

    private function region(): string
    {
        return self::REGIONS[$this->randomizer->getInt(0, count(self::REGIONS) - 1)];
    }
}
