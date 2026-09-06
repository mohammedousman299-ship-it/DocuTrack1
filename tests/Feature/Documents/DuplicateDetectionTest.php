<?php

declare(strict_types=1);

use App\Documents\DuplicateDetector;
use App\Enums\FoundReportStatus;
use App\Models\DocumentType;
use App\Models\FoundReport;

function typeDeTest(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'doublon_test'],
        ['label_fr' => 'Type', 'label_en' => 'Type', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

it('repère un doublon écrit différemment', function (): void {
    // Le contrôle porte sur une empreinte normalisée : deux personnes ne
    // saisissent pas de la même façon (§4.5).
    $type = typeDeTest();
    FoundReport::factory()->withNumber('AB-123 456')->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Ölanda Miro',
    ]);

    $trouves = DuplicateDetector::existingFor($type->id, 'ab123456', 'miro olanda');

    expect($trouves)->toHaveCount(1);
});

it('ne confond pas deux documents de types différents', function (): void {
    $type = typeDeTest();
    $autre = DocumentType::firstOrCreate(
        ['code' => 'doublon_autre'],
        ['label_fr' => 'Autre', 'label_en' => 'Other', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
    FoundReport::factory()->withNumber('AB123456')->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
    ]);

    expect(DuplicateDetector::existingFor($autre->id, 'AB123456', 'Miro Olanda'))->toBeEmpty();
});

it('ignore les signalements rejetés ou expirés', function (): void {
    // Ils ne sont plus dans l'index : ils ne font pas doublon.
    $type = typeDeTest();
    foreach ([FoundReportStatus::Rejected, FoundReportStatus::Expired] as $statut) {
        FoundReport::factory()->withNumber('CD999888')->create([
            'document_type_id' => $type->id,
            'owner_name' => 'Tavi Bekundi',
            'status' => $statut,
        ]);
    }

    expect(DuplicateDetector::existingFor($type->id, 'CD999888', 'Tavi Bekundi'))->toBeEmpty();
});

it('met en revue plutôt que de rejeter un doublon', function (): void {
    // Refuser un signalement légitime coûte plus cher qu'un doublon à trier :
    // chaque signalement perdu est un document non restitué.
    expect(DuplicateDetector::statusFor(hasDuplicate: true, reporterScore: 50, missingRequiredAttachment: false))
        ->toBe(FoundReportStatus::PendingReview);
});

it('met en revue les signalements d’un Trouveur peu fiable', function (): void {
    // Contre l'injection dans l'index de rapprochement (M-06).
    expect(DuplicateDetector::statusFor(false, 10, false))->toBe(FoundReportStatus::PendingReview);
});

it('met en revue un type sensible sans pièce jointe', function (): void {
    // Sans image, la revue humaine imposée sur ces types n'aurait rien à
    // vérifier et deviendrait décorative (D-030, M-01).
    expect(DuplicateDetector::statusFor(false, 50, true))->toBe(FoundReportStatus::PendingReview);
});

it('active directement un signalement ordinaire', function (): void {
    expect(DuplicateDetector::statusFor(false, 50, false))->toBe(FoundReportStatus::Active);
});

it('n’inclut pas le signalement en cours de modification', function (): void {
    $type = typeDeTest();
    $report = FoundReport::factory()->withNumber('EF111222')->create([
        'document_type_id' => $type->id,
        'owner_name' => 'Ndzomo Ayo',
    ]);

    expect(DuplicateDetector::existingFor($type->id, 'EF111222', 'Ndzomo Ayo', $report->id))
        ->toBeEmpty();
});
