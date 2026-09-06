<?php

declare(strict_types=1);

use App\Documents\ExifStripper;
use App\Jobs\VerifyAttachmentMetadata;
use App\Models\ReportAttachment;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixture;

it('détecte des coordonnées de géolocalisation dans une image', function (): void {
    // Sans ce préalable, les tests suivants ne prouveraient rien.
    expect(ExifStripper::hasLocation(ImageFixture::jpegWithGps()))->toBeTrue()
        ->and(ExifStripper::hasLocation(ImageFixture::plainJpeg()))->toBeFalse();
});

it('retire réellement les coordonnées de géolocalisation', function (): void {
    // Une photo de pièce d'identité indique très souvent où habite ou
    // travaille son propriétaire (M-12).
    $avecGps = ImageFixture::jpegWithGps();

    $nettoyee = ExifStripper::strip($avecGps);

    expect(ExifStripper::hasLocation($nettoyee))->toBeFalse()
        ->and(ExifStripper::hasMetadata($nettoyee))->toBeFalse();
});

it('produit toujours une image décodable', function (): void {
    $nettoyee = ExifStripper::strip(ImageFixture::jpegWithGps());

    expect(@imagecreatefromstring($nettoyee))->not->toBeFalse();
});

it('refuse un contenu qui n’est pas une image', function (): void {
    expect(fn () => ExifStripper::strip('ceci nest pas une image'))
        ->toThrow(RuntimeException::class);
});

it('marque une pièce servable après vérification serveur', function (): void {
    Storage::fake('s3');
    $attachment = ReportAttachment::factory()->create();
    Storage::disk('s3')->put($attachment->object_key, ImageFixture::plainJpeg());

    expect($attachment->isServable())->toBeFalse();

    (new VerifyAttachmentMetadata($attachment->id))->handle();

    expect($attachment->fresh()->isServable())->toBeTrue();
});

it('renettoie une image dont le client n’a pas retiré les métadonnées', function (): void {
    // Le nettoyage client n'est pas une preuve : un attaquant détenant une URL
    // pré-signée dépose ce qu'il veut sans passer par notre interface (D-029).
    Storage::fake('s3');
    $attachment = ReportAttachment::factory()->create();
    Storage::disk('s3')->put($attachment->object_key, ImageFixture::jpegWithGps());

    (new VerifyAttachmentMetadata($attachment->id))->handle();

    $stocke = (string) Storage::disk('s3')->get($attachment->object_key);

    expect(ExifStripper::hasLocation($stocke))->toBeFalse()
        ->and($attachment->fresh()->isServable())->toBeTrue();
});

it('laisse la pièce non servable si le fichier est absent', function (): void {
    // L'envoi n'a pas abouti : la purge de rétention l'emportera.
    Storage::fake('s3');
    $attachment = ReportAttachment::factory()->create();

    (new VerifyAttachmentMetadata($attachment->id))->handle();

    expect($attachment->fresh()->isServable())->toBeFalse();
});

it('est idempotente : rejouer la tâche ne change rien', function (): void {
    Storage::fake('s3');
    $attachment = ReportAttachment::factory()->create();
    Storage::disk('s3')->put($attachment->object_key, ImageFixture::plainJpeg());

    (new VerifyAttachmentMetadata($attachment->id))->handle();
    $premiere = $attachment->fresh()->exif_stripped_at;

    (new VerifyAttachmentMetadata($attachment->id))->handle();

    expect($attachment->fresh()->exif_stripped_at->equalTo($premiere))->toBeTrue();
});

it('recalcule la taille et l’empreinte après nettoyage', function (): void {
    Storage::fake('s3');
    $attachment = ReportAttachment::factory()->create(['byte_size' => 999999]);
    Storage::disk('s3')->put($attachment->object_key, ImageFixture::jpegWithGps());

    (new VerifyAttachmentMetadata($attachment->id))->handle();

    $frais = $attachment->fresh();
    expect($frais->byte_size)->toBeLessThan(999999)
        ->and($frais->content_hash)->toBe(hash('sha256', (string) Storage::disk('s3')->get($frais->object_key)));
});
