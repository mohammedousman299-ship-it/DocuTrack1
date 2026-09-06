<?php

declare(strict_types=1);

use App\Models\FoundReport;
use App\Models\ReportAttachment;

it('ne considère jamais servable une pièce jointe non nettoyée', function (): void {
    // C'est ce qui rend acceptable l'envoi direct depuis le navigateur
    // (D-029) : le nettoyage client n'est pas une preuve, la vérification
    // serveur en est une.
    $brute = ReportAttachment::factory()->create();
    $nettoyee = ReportAttachment::factory()->stripped()->create();

    expect($brute->isServable())->toBeFalse()
        ->and($nettoyee->isServable())->toBeTrue();
});

it('exclut les pièces non nettoyées du scope servable', function (): void {
    ReportAttachment::factory()->count(3)->create();
    ReportAttachment::factory()->stripped()->count(2)->create();

    expect(ReportAttachment::servable()->count())->toBe(2)
        ->and(ReportAttachment::count())->toBe(5);
});

it('ne sérialise jamais la clé d’objet', function (): void {
    // Une clé connue rend l'objet adressable : elle annulerait le bénéfice du
    // bucket privé si elle atteignait le navigateur (M-12).
    $serialise = ReportAttachment::factory()->stripped()->create()->toArray();

    expect($serialise)->not->toHaveKey('object_key')
        ->and($serialise)->not->toHaveKey('content_hash');
});

it('ne sérialise jamais les champs chiffrés d’un signalement', function (): void {
    $serialise = FoundReport::factory()->withNumber('AB123456')->create()->toArray();

    expect($serialise)->not->toHaveKeys([
        'number_encrypted', 'number_hmac', 'number_last4_encrypted',
        'deposit_reference_encrypted', 'duplicate_fingerprint',
    ]);
});

it('n’expose aucun retour de rapprochement sur un signalement', function (): void {
    // Un compteur ou un statut de rapprochement lisible par le Trouveur
    // transformerait les faux signalements en canal d'extraction (M-06).
    $colonnes = array_keys(FoundReport::factory()->create()->getAttributes());

    expect($colonnes)->not->toContain('match_count')
        ->and($colonnes)->not->toContain('matches_count')
        ->and($colonnes)->not->toContain('notified_owners');
});
