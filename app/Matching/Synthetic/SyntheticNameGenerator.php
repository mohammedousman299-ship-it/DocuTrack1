<?php

declare(strict_types=1);

namespace App\Matching\Synthetic;

/**
 * Générateur de noms ENTIÈREMENT FICTIFS pour les jeux de test.
 *
 * §12 du master prompt : jamais de données réelles de personnes dans le dépôt,
 * les tests, les captures d'écran ou les jeux de démonstration.
 *
 * Les noms sont produits par combinaison algorithmique de syllabes inventées.
 * Ils ne sont tirés d'aucune liste de personnes, d'aucun annuaire et d'aucune
 * source publique. Le procédé étant combinatoire, une coïncidence avec un nom
 * réel reste possible : elle serait fortuite et sans rapport avec une personne
 * identifiable.
 *
 * La graine rend la génération déterministe : deux exécutions produisent le
 * même corpus, sans quoi les métriques du jalon 5 ne seraient pas comparables
 * d'une mesure à l'autre.
 */
final class SyntheticNameGenerator
{
    /** @var list<string> */
    private const ONSETS = [
        'mir', 'ol', 'tav', 'bek', 'ndz', 'ayo', 'kum', 'zel', 'far', 'nyo',
        'vek', 'jal', 'rud', 'som', 'til', 'web', 'xan', 'pol', 'gur', 'hep',
    ];

    /** @var list<string> */
    private const CODAS = [
        'anda', 'undi', 'ombe', 'ira', 'ako', 'essi', 'ulu', 'ente', 'oro', 'iba',
        'awe', 'uzo', 'ekan', 'ilo', 'onga', 'urse', 'atu', 'emba', 'ozi', 'ade',
    ];

    private int $counter = 0;

    public function __construct(private readonly int $seed = 20260905)
    {
        mt_srand($this->seed);
    }

    /** Un token de nom, par exemple « mirandа » ou « bekundi ». */
    public function token(): string
    {
        $onset = self::ONSETS[mt_rand(0, count(self::ONSETS) - 1)];
        $coda = self::CODAS[mt_rand(0, count(self::CODAS) - 1)];

        return ucfirst($onset.$coda);
    }

    /** Un nom complet de 2 à 4 tokens, comme on en rencontre couramment. */
    public function fullName(?int $tokenCount = null): string
    {
        $count = $tokenCount ?? (mt_rand(1, 100) <= 70 ? 2 : (mt_rand(0, 1) === 0 ? 3 : 4));

        $tokens = [];
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $this->token();
        }

        return implode(' ', $tokens);
    }

    /** Un numéro de document plausible, sans prétendre reproduire un format officiel. */
    public function documentNumber(): string
    {
        $this->counter++;

        return sprintf('%s%08d', chr(mt_rand(65, 90)), $this->counter);
    }

    /** Introduit exactement $count fautes de frappe : la plus fréquente des erreurs. */
    public function withTypos(string $value, int $count = 1): string
    {
        $chars = mb_str_split($value);
        $letterPositions = [];

        foreach ($chars as $index => $char) {
            if (preg_match('/[a-z]/i', $char) === 1) {
                $letterPositions[] = $index;
            }
        }

        if ($letterPositions === []) {
            return $value;
        }

        for ($i = 0; $i < $count; $i++) {
            $position = $letterPositions[mt_rand(0, count($letterPositions) - 1)];
            $chars[$position] = chr(mt_rand(97, 122));
        }

        return implode('', $chars);
    }

    /** Inverse l'ordre des tokens : très fréquent sur les formulaires. */
    public function shuffleTokens(string $name): string
    {
        $tokens = explode(' ', $name);

        return implode(' ', array_reverse($tokens));
    }
}
