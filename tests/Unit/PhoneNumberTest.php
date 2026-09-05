<?php

declare(strict_types=1);

use App\Auth\PhoneNumber;

it('fait converger toutes les graphies d’un même abonné', function (): void {
    // L'unicité du numéro est le contrôle anti-Sybil principal (D-013). Elle
    // n'a de sens que si deux graphies du même numéro se heurtent en base.
    $graphies = [
        '+237 6 12 34 56 78',
        '+237612345678',
        '237612345678',      // indicatif sans le « + »
        '00237612345678',    // préfixe international composé
        '0612345678',        // national avec zéro initial
        '612345678',         // national sans zéro
    ];

    $normalisees = array_unique(array_map(
        static fn (string $g): ?string => PhoneNumber::normalize($g),
        $graphies
    ));

    expect($normalisees)->toHaveCount(1)
        ->and(reset($normalisees))->toBe('+237612345678');
});

it('ne préfixe pas deux fois l’indicatif', function (): void {
    expect(PhoneNumber::normalize('237612345678'))->toBe('+237612345678');
});

it('conserve un indicatif étranger explicite', function (): void {
    expect(PhoneNumber::normalize('+33 6 12 34 56 78'))->toBe('+33612345678');
});

it('rejette une saisie sans chiffre', function (): void {
    expect(PhoneNumber::normalize('abc'))->toBeNull()
        ->and(PhoneNumber::normalize(''))->toBeNull()
        ->and(PhoneNumber::normalize(null))->toBeNull();
});

it('valide la forme E.164 sans supposer un plan de numérotation', function (): void {
    // Les préfixes exacts des numéros camerounais ne sont pas connus et ne
    // sont pas inventés : la validation reste structurelle.
    expect(PhoneNumber::isPlausible('+237612345678'))->toBeTrue()
        ->and(PhoneNumber::isPlausible('+2376'))->toBeFalse()
        ->and(PhoneNumber::isPlausible('+0123456789'))->toBeFalse()
        ->and(PhoneNumber::isPlausible(null))->toBeFalse();
});
