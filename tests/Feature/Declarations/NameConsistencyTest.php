<?php

declare(strict_types=1);

use App\Documents\NameConsistencyCheck;
use App\Enums\NameConsistency;
use App\Models\LostDeclaration;

it('reconnaît un nom identique, quelle que soit la graphie', function (): void {
    expect(NameConsistencyCheck::compare('Ölanda Miro', 'miro olanda'))
        ->toBe(NameConsistency::Match);
});

it('accepte un prénom omis à l’inscription', function (): void {
    // Un compte « Miro Olanda » couvre une déclaration « Ayo Miro Olanda » :
    // un second prénom simplement non saisi à l'inscription.
    expect(NameConsistencyCheck::compare('Miro Olanda', 'Ayo Miro Olanda'))
        ->toBe(NameConsistency::Match)
        ->and(NameConsistencyCheck::compare('Ayo Miro Olanda', 'Miro Olanda'))
        ->toBe(NameConsistency::Match);
});

it('refuse un seul token commun', function (): void {
    // Les prénoms se répètent : un token partagé ne prouve rien, et laisser
    // passer sur ce critère rendrait le contrôle décoratif (M-02).
    expect(NameConsistencyCheck::compare('Miro Olanda', 'Miro Bekundi'))
        ->toBe(NameConsistency::Mismatch);
});

it('détecte une déclaration au nom d’un tiers', function (): void {
    // C'est le vecteur le moins coûteux du système : déclarer au nom d'autrui
    // pour être prévenu avant lui.
    expect(NameConsistencyCheck::compare('Miro Olanda', 'Tavi Ndzomo'))
        ->toBe(NameConsistency::Mismatch);
});

it('ne tranche pas en l’absence de nom', function (): void {
    expect(NameConsistencyCheck::compare(null, 'Miro Olanda'))->toBe(NameConsistency::Unknown)
        ->and(NameConsistencyCheck::compare('Miro Olanda', ''))->toBe(NameConsistency::Unknown);
});

it('accepte l’inversion nom/prénom', function (): void {
    expect(NameConsistencyCheck::compare('Olanda Miro', 'Miro Olanda'))
        ->toBe(NameConsistency::Match);
});

it('n’autorise aucune notification automatique sur une incohérence', function (): void {
    // La déclaration est acceptée — déclarer pour un proche est légitime —
    // mais elle perd l'automatisme et part en revue (D-036).
    $incoherente = LostDeclaration::factory()->mismatchedName()->create();
    $coherente = LostDeclaration::factory()->create();

    expect($incoherente->allowsAutomaticNotification())->toBeFalse()
        ->and($coherente->allowsAutomaticNotification())->toBeTrue();
});

it('ne laisse jamais le numéro en clair dans la base', function (): void {
    $declaration = LostDeclaration::factory()->withNumber('AB-123 456')->create();

    $brut = DB::table('lost_declarations')->where('id', $declaration->id)->first();

    expect($brut->number_encrypted)->not->toContain('AB123456')
        ->and($declaration->fresh()->numberInClear())->toBe('AB123456');
});
