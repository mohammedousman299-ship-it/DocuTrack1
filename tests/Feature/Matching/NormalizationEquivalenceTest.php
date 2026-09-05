<?php

declare(strict_types=1);

use App\Matching\DocumentNumberNormalizer;
use App\Matching\NameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Test d'équivalence SQL ↔ PHP.
 *
 * La normalisation est implémentée deux fois : en SQL pour les index et les
 * requêtes, en PHP pour la validation et les tests. Une divergence entre les
 * deux produirait des correspondances irreproductibles selon le chemin
 * emprunté — un bug quasi impossible à diagnostiquer en production.
 *
 * Ce test est la seule chose qui garantit qu'elles restent alignées.
 *
 * Tous les noms sont SYNTHÉTIQUES (§12 du master prompt).
 */

/** @return list<string> */
function normalizationNameCorpus(): array
{
    return [
        'Miro Olanda',
        'Olanda Miro',            // ordre inversé
        'MIRO OLANDA',
        'miro  olanda',           // espaces multiples
        'Ölanda Mírö',            // accents décomposables
        "Ol'anda Miro",           // apostrophe droite
        "Ol\u{2019}anda Miro",    // apostrophe courbe
        'Miro-Ayo Olanda',        // nom composé
        'Ayo Miro Olanda',        // trois tokens
        'Tavi Ayo Miro Olanda',   // quatre tokens
        'Øland Æmil',             // non décomposables
        'Straße Ndolo',           // ß
        'Œuvre Tavi',             // ligature
        '  Miro   Olanda  ',      // espaces en bordure
        'Miro.Olanda',            // point séparateur
        'Miro_Olanda',            // souligné
        'Bekundi 2e',             // chiffre dans un token
        'a',                      // token minimal
        '',                       // vide
        '   ',                    // blancs seulement
        '---',                    // ponctuation seulement
    ];
}

/** @return list<string> */
function normalizationNumberCorpus(): array
{
    return [
        '102938475',
        '102-938 475',
        'aO12I4',                 // homoglyphes minuscules
        'AO12I4',
        'A0121 4',
        'cm/2026/00123',
        '  spaced  ',
        'ßeta9',                  // caractère non ASCII
        '',
        '///',
    ];
}

it('produit des noms normalisés identiques en SQL et en PHP', function (): void {
    foreach (normalizationNameCorpus() as $input) {
        $sql = DB::selectOne(
            'select docutrack_normalize_name(?) as value',
            [$input]
        )->value;

        expect(NameNormalizer::normalize($input))
            ->toBe($sql, 'divergence sur le nom : '.var_export($input, true));
    }
});

it('produit des numéros normalisés identiques en SQL et en PHP', function (): void {
    foreach (normalizationNumberCorpus() as $input) {
        $sql = DB::selectOne(
            'select docutrack_normalize_number(?) as value',
            [$input]
        )->value;

        expect(DocumentNumberNormalizer::normalize($input))
            ->toBe($sql, 'divergence sur le numéro : '.var_export($input, true));
    }
});

it('trie les tokens, ce qui rend deux graphies inversées identiques', function (): void {
    expect(NameNormalizer::normalize('Miro Olanda'))
        ->toBe(NameNormalizer::normalize('Olanda Miro'));
});

it('supprime les apostrophes sans découper le token', function (): void {
    expect(NameNormalizer::normalize("Ol'anda"))->toBe('olanda');
});

it('traite le tiret comme un séparateur, contrairement à l’apostrophe', function (): void {
    expect(NameNormalizer::normalize('Miro-Ayo'))->toBe('ayo miro');
});

it('ne replie que les homoglyphes O et I, jamais S ni B', function (): void {
    expect(DocumentNumberNormalizer::normalize('OI'))->toBe('01')
        ->and(DocumentNumberNormalizer::normalize('SB'))->toBe('SB');
});
