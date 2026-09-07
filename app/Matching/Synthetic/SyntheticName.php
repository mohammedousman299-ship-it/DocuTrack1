<?php

declare(strict_types=1);

namespace App\Matching\Synthetic;

use Random\Randomizer;

/**
 * Noms ENTIÈREMENT INVENTÉS, produits par combinaison de syllabes.
 *
 * ------------------------------------------------------------------------
 * Aucune donnée réelle de personne n'entre dans le dépôt, les tests, les
 * captures d'écran ou les jeux de démonstration (§12 du master prompt).
 *
 * Les syllabes ci-dessous ne sont ni des noms, ni des fragments de noms
 * existants : ce sont des groupes consonne-voyelle choisis pour produire une
 * structure phonétique plausible sans désigner personne. Aucun nom de
 * personnalité publique n'y figure, même à titre d'exemple — c'est
 * exactement l'infraction relevée dans le prototype (AUDIT_PROTOTYPE.md V1),
 * et je l'ai moi-même commise une fois au jalon 1 en réutilisant des données
 * de démonstration comme exemples de normalisation.
 *
 * Le risque résiduel — une combinaison tombant par hasard sur un nom réel —
 * ne peut pas être écarté et n'a pas besoin de l'être : ces noms ne
 * PRÉTENDENT désigner personne, ne sont associés à aucune donnée réelle, et
 * ne quittent pas le jeu de mesure.
 * ------------------------------------------------------------------------
 *
 * Le générateur est DÉTERMINISTE à graine donnée : une mesure qu'on ne peut
 * pas rejouer sur le même jeu ne se compare à rien.
 */
final class SyntheticName
{
    /** @var list<string> */
    private const ONSETS = [
        'b', 'd', 'f', 'g', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'z',
        'bw', 'dj', 'kw', 'mb', 'nd', 'ng', 'ny', 'ts',
    ];

    /** @var list<string> */
    private const NUCLEI = ['a', 'e', 'i', 'o', 'ou', 'é', 'è', 'a', 'i', 'o'];

    /** @var list<string> */
    private const CODAS = ['', '', '', 'm', 'n', 'l', 'r', 's'];

    public function __construct(private readonly Randomizer $randomizer) {}

    /** Un token de 2 ou 3 syllabes. */
    public function token(): string
    {
        $syllables = $this->randomizer->getInt(2, 3);
        $word = '';

        for ($i = 0; $i < $syllables; $i++) {
            $word .= $this->pick(self::ONSETS).$this->pick(self::NUCLEI);
            $word .= $i === $syllables - 1 ? $this->pick(self::CODAS) : '';
        }

        return mb_convert_case($word, MB_CASE_TITLE);
    }

    /**
     * Token COURT d'une seule syllabe.
     *
     * Existe pour éprouver le plancher de longueur du score de nom : sur un
     * nom de 4 caractères, la distance d'édition normalisée donne 0,75 à une
     * seule lettre d'écart. Sans nom court dans le jeu, ce plancher ne serait
     * jamais mis à l'épreuve et sa valeur resterait une supposition.
     */
    public function shortToken(): string
    {
        return mb_convert_case(
            $this->pick(self::ONSETS).$this->pick(self::NUCLEI).$this->pick(self::CODAS),
            MB_CASE_TITLE
        );
    }

    /** Un nom complet de `$tokens` tokens. */
    public function fullName(int $tokens = 2): string
    {
        $parts = [];

        for ($i = 0; $i < $tokens; $i++) {
            $parts[] = $this->token();
        }

        return implode(' ', $parts);
    }

    /**
     * Faute de frappe : substitution, suppression ou insertion d'un caractère.
     *
     * Les caractères touchés sont choisis hors des espaces, sans quoi la
     * « faute » fusionnerait deux tokens — un cas différent, déjà couvert par
     * la famille des noms inversés.
     */
    public function withTypo(string $name, int $count = 1): string
    {
        // Une substitution peut retomber sur le caractère d'origine, et deux
        // fautes peuvent s'annuler : la « faute » produite serait alors le nom
        // lui-même, et la famille name_typo mesurerait une correspondance
        // exacte sous une étiquette de faute de frappe. On réessaie jusqu'à ce
        // que le nom change vraiment, avec une borne pour ne jamais boucler.
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $mutated = $this->applyTypos($name, $count);

            if ($mutated !== $name) {
                return $mutated;
            }
        }

        return $mutated;
    }

    private function applyTypos(string $name, int $count): string
    {
        $characters = mb_str_split($name);

        for ($i = 0; $i < $count; $i++) {
            $positions = array_keys(array_filter(
                $characters,
                static fn (string $c): bool => $c !== ' '
            ));

            if ($positions === []) {
                break;
            }

            $at = $positions[$this->randomizer->getInt(0, count($positions) - 1)];

            $characters[$at] = match ($this->randomizer->getInt(0, 2)) {
                0 => $this->pick(self::ONSETS),       // substitution
                1 => '',                              // suppression
                default => $characters[$at].$this->pick(self::NUCLEI), // insertion
            };
        }

        return implode('', $characters);
    }

    /**
     * Nom d'une AUTRE personne partageant un token avec `$name`.
     *
     * Cas parfaitement banal — deux personnes de même patronyme — et l'un des
     * rares que le moteur peut réellement confondre : sans numéro d'aucun
     * côté, le nom porte seul la décision.
     */
    public function sharingToken(string $name): string
    {
        $tokens = explode(' ', $name);
        $keep = $this->randomizer->getInt(0, count($tokens) - 1);

        foreach (array_keys($tokens) as $i) {
            if ($i !== $keep) {
                $tokens[$i] = $this->token();
            }
        }

        return implode(' ', $tokens);
    }

    /** Permute les tokens : « Prénom Nom » écrit « Nom Prénom ». */
    public function swapped(string $name): string
    {
        $tokens = explode(' ', $name);

        return implode(' ', array_reverse($tokens));
    }

    /** @param list<string> $values */
    private function pick(array $values): string
    {
        return $values[$this->randomizer->getInt(0, count($values) - 1)];
    }
}
