<?php

declare(strict_types=1);

use App\Enums\AdminRole;
use App\Enums\FoundReportStatus;
use App\Models\FoundReport;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('empêche un utilisateur de voir le signalement d’un autre', function (): void {
    $report = FoundReport::factory()->create();

    expect(Gate::forUser(User::factory()->create())->allows('view', $report))->toBeFalse()
        ->and(Gate::forUser($report->finder)->allows('view', $report))->toBeTrue();
});

it('n’autorise AUCUN utilisateur à voir une image, pas même son auteur', function (): void {
    // D-006 : l'image n'est visible d'aucun utilisateur, à aucun niveau, y
    // compris N3. Elle est fournie par un tiers sur une personne qui n'a pas
    // consenti.
    $report = FoundReport::factory()->create();
    $attachment = ReportAttachment::factory()->stripped()->create([
        'found_report_id' => $report->id,
    ]);

    expect(Gate::forUser($report->finder)->allows('view', $attachment))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('view', $attachment))->toBeFalse();
});

it('refuse l’image à un administrateur fonctionnel', function (): void {
    // La séparation des deux rôles d'administration est un contrôle
    // préventif, là où la journalisation seule serait détective (D-014).
    $attachment = ReportAttachment::factory()->stripped()->create();
    $fonctionnel = User::factory()->admin(AdminRole::Functional)->create();

    expect(Gate::forUser($fonctionnel)->allows('view', $attachment))->toBeFalse();
});

it('autorise l’image au seul relecteur sensible', function (): void {
    $attachment = ReportAttachment::factory()->stripped()->create();
    $sensible = User::factory()->admin(AdminRole::Sensitive)->create();

    expect(Gate::forUser($sensible)->allows('view', $attachment))->toBeTrue();
});

it('refuse une image non vérifiée même au relecteur sensible', function (): void {
    // Refuser de servir vaut mieux que servir une image dont on n'a pas
    // confirmé le nettoyage (D-029).
    $nonVerifiee = ReportAttachment::factory()->create(); // exif_stripped_at nul
    $sensible = User::factory()->admin(AdminRole::Sensitive)->create();

    expect(Gate::forUser($sensible)->allows('view', $nonVerifiee))->toBeFalse()
        ->and(Gate::forUser($sensible)->allows('generateSignedUrl', $nonVerifiee))->toBeFalse();
});

it('interdit à un Trouveur de revendiquer son propre signalement', function (): void {
    // Sinon un compte s'auto-attribue un document (D-004). La contrainte
    // existe aussi en base : deux barrières valent mieux qu'une.
    $report = FoundReport::factory()->create();

    expect(Gate::forUser($report->finder)->allows('claim', $report))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('claim', $report))->toBeTrue();
});

it('empêche la modification d’un signalement déjà rapproché', function (): void {
    $report = FoundReport::factory()->create(['status' => FoundReportStatus::Matched]);

    expect(Gate::forUser($report->finder)->allows('update', $report))->toBeFalse();
});

it('réserve les champs sensibles à la revue sensible', function (): void {
    $report = FoundReport::factory()->create();

    expect(Gate::forUser($report->finder)->allows('viewSensitiveFields', $report))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin(AdminRole::Sensitive)->create())
            ->allows('viewSensitiveFields', $report))->toBeTrue();
});
