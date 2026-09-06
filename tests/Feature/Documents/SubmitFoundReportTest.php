<?php

declare(strict_types=1);

use App\Documents\SubmitFoundReport;
use App\Documents\Upload\UploadTicket;
use App\Enums\FoundReportStatus;
use App\Jobs\VerifyAttachmentMetadata;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixture;

function typeStandard(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'soumission_std'],
        ['label_fr' => 'Standard', 'label_en' => 'Standard', 'sensitivity' => 'standard', 'retention_days' => 180]
    );
}

function typeSensible(): DocumentType
{
    return DocumentType::firstOrCreate(
        ['code' => 'soumission_cni'],
        ['label_fr' => 'CNI', 'label_en' => 'ID', 'sensitivity' => 'high', 'retention_days' => 180]
    );
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function donneesSignalement(DocumentType $type, array $extra = []): array
{
    return array_merge([
        'document_type_id' => $type->id,
        'owner_name' => 'Miro Olanda',
        'document_number' => 'AB123456',
        'found_on' => now()->toDateString(),
        'found_region' => 'Centre',
        'found_city' => 'Ville',
    ], $extra);
}

it('enregistre un signalement ordinaire directement actif', function (): void {
    $report = (new SubmitFoundReport)->handle(User::factory()->create(), donneesSignalement(typeStandard()));

    expect($report->status)->toBe(FoundReportStatus::Active)
        ->and($report->owner_name_normalized)->toBe('miro olanda');
});

it('met en revue un doublon plutôt que de le rejeter', function (): void {
    $type = typeStandard();
    $service = new SubmitFoundReport;

    $service->handle(User::factory()->create(), donneesSignalement($type));
    $second = $service->handle(User::factory()->create(), donneesSignalement($type));

    expect($second->status)->toBe(FoundReportStatus::PendingReview)
        ->and(FoundReport::count())->toBe(2);
});

it('met en revue un type sensible sans photo', function (): void {
    // Sans image, la revue humaine imposée sur ces types n'aurait rien à
    // vérifier (D-030, M-01).
    $report = (new SubmitFoundReport)->handle(
        User::factory()->create(),
        donneesSignalement(typeSensible())
    );

    expect($report->status)->toBe(FoundReportStatus::PendingReview);
});

it('rattache l’image et déclenche sa vérification serveur', function (): void {
    Storage::fake('s3');
    Queue::fake();

    $finder = User::factory()->create();
    $ticket = UploadTicket::issue($finder);
    Storage::disk('s3')->put($ticket->objectKey, ImageFixture::plainJpeg());

    $report = (new SubmitFoundReport)->handle(
        $finder,
        donneesSignalement(typeStandard(), ['object_key' => $ticket->objectKey])
    );

    expect($report->attachments)->toHaveCount(1)
        ->and($report->attachments->first()->isServable())->toBeFalse();

    Queue::assertPushed(VerifyAttachmentMetadata::class);
});

it('refuse une clé d’objet demandée par un autre compte', function (): void {
    // Sans ce contrôle, un compte rattacherait à son signalement l'image
    // envoyée par quelqu'un d'autre.
    Storage::fake('s3');
    $proprietaireDuTicket = User::factory()->create();
    $attaquant = User::factory()->create();
    $ticket = UploadTicket::issue($proprietaireDuTicket);
    Storage::disk('s3')->put($ticket->objectKey, ImageFixture::plainJpeg());

    $report = (new SubmitFoundReport)->handle(
        $attaquant,
        donneesSignalement(typeStandard(), ['object_key' => $ticket->objectKey])
    );

    expect($report->attachments)->toHaveCount(0);
});

it('consomme le ticket : une clé ne sert qu’une fois', function (): void {
    Storage::fake('s3');
    $finder = User::factory()->create();
    $ticket = UploadTicket::issue($finder);
    Storage::disk('s3')->put($ticket->objectKey, ImageFixture::plainJpeg());

    (new SubmitFoundReport)->handle(
        $finder,
        donneesSignalement(typeStandard(), ['object_key' => $ticket->objectKey])
    );

    expect(UploadTicket::belongsTo($ticket->objectKey, $finder))->toBeFalse();
});

it('génère une clé d’objet non devinable, jamais fournie par le client', function (): void {
    // Une clé choisie par le client permettrait d'écraser l'objet d'autrui.
    $a = UploadTicket::issue(User::factory()->create());
    $b = UploadTicket::issue(User::factory()->create());

    expect($a->objectKey)->not->toBe($b->objectKey)
        ->and($a->objectKey)->toStartWith('documents/')
        ->and($a->objectKey)->toMatch('#^documents/[0-9a-f-]{36}\.jpg$#');
});

it('applique la rétention du type de document', function (): void {
    $report = (new SubmitFoundReport)->handle(User::factory()->create(), donneesSignalement(typeStandard()));

    // diffInDays est signé : on mesure depuis maintenant vers l'échéance.
    expect(now()->diffInDays($report->expires_at))->toBeGreaterThan(179);
});
