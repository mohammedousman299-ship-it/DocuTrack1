<?php

declare(strict_types=1);

use App\Documents\DocumentNumber;
use App\Documents\DuplicateFingerprint;
use App\Models\FoundReport;
use Illuminate\Support\Facades\DB;

it('ne laisse jamais le numéro en clair dans la base', function (): void {
    // C'est la garantie centrale de D-007 : une fuite de base ou de sauvegarde
    // ne doit livrer aucun numéro de pièce d'identité.
    $report = FoundReport::factory()->withNumber('AB-123 456')->create();

    $brut = DB::table('found_reports')->where('id', $report->id)->first();

    expect($brut->number_encrypted)->not->toContain('AB123456')
        ->and($brut->number_encrypted)->not->toContain('AB-123 456')
        ->and($report->fresh()->numberInClear())->toBe('AB123456');
});

it('fait converger deux graphies du même numéro vers le même HMAC', function (): void {
    // Sans cela, le contrôle de doublon et le rapprochement par égalité
    // exacte manqueraient des correspondances évidentes.
    expect(DocumentNumber::hmacForInput('AB-123 456'))
        ->toBe(DocumentNumber::hmacForInput('ab123456'));
});

it('replie les homoglyphes O et I dans le HMAC', function (): void {
    expect(DocumentNumber::hmacForInput('AO12I4'))
        ->toBe(DocumentNumber::hmacForInput('A01214'));
});

it('traite un numéro absent comme neutre, jamais comme une valeur vide', function (): void {
    $number = DocumentNumber::fromInput(null);

    expect($number->isPresent())->toBeFalse()
        ->and($number->hmac)->toBeNull()
        ->and($number->normalized)->toBeNull();
});

it('refuse de calculer un HMAC sans clé serveur', function (): void {
    // Une clé vide rendrait les empreintes recalculables par quiconque obtient
    // la base — ce qui annulerait tout le bénéfice de D-007.
    config()->set('docutrack.document_number_hmac_key', '');

    expect(fn () => DocumentNumber::hmac('AB123456'))
        ->toThrow(RuntimeException::class, 'DOCUMENT_NUMBER_HMAC_KEY');
});

it('dérive l’empreinte de doublon des formes normalisées', function (): void {
    // Le contrôle porte sur une empreinte normalisée, pas sur une égalité
    // stricte : deux personnes ne saisissent pas de la même façon (§4.5).
    $a = DuplicateFingerprint::compute(1, 'AB-123 456', 'Ölanda Miro');
    $b = DuplicateFingerprint::compute(1, 'ab123456', 'miro olanda');

    expect($a)->toBe($b);
});

it('distingue deux documents de types différents', function (): void {
    expect(DuplicateFingerprint::compute(1, 'AB123456', 'Miro Olanda'))
        ->not->toBe(DuplicateFingerprint::compute(2, 'AB123456', 'Miro Olanda'));
});

it('recalcule l’empreinte et le nom normalisé à chaque enregistrement', function (): void {
    // Une dérive entre ces champs dérivés et leurs sources fausserait le
    // rapprochement sans que rien ne le signale.
    $report = FoundReport::factory()->create(['owner_name' => 'Ölanda Miro']);
    $empreinteInitiale = $report->duplicate_fingerprint;

    $report->update(['owner_name' => 'Bekundi Tavi']);
    $report->refresh();

    expect($report->owner_name_normalized)->toBe('bekundi tavi')
        ->and($report->duplicate_fingerprint)->not->toBe($empreinteInitiale);
});
