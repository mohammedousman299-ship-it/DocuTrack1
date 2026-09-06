<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Documents\ExifStripper;
use App\Models\ReportAttachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Vérifie, côté serveur, qu'une pièce jointe est dépourvue de métadonnées.
 *
 * C'est la moitié serveur de D-029, qui résout une contradiction du cahier des
 * charges : le §3.3 interdit que l'image transite par le conteneur PHP, le
 * modèle de menaces exige un nettoyage serveur. Le navigateur nettoie et
 * envoie directement ; cette tâche vérifie ensuite, renettoie si nécessaire, et
 * ne marque la pièce servable qu'à ce moment.
 *
 * Sans elle, un attaquant détenant une URL pré-signée déposerait ce qu'il veut
 * sans jamais passer par notre code, et le garde-fou « suppression EXIF
 * systématique côté serveur » serait une fiction.
 */
final class VerifyAttachmentMetadata implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $attachmentId) {}

    public function handle(): void
    {
        $attachment = ReportAttachment::find($this->attachmentId);

        if ($attachment === null || $attachment->isServable()) {
            return; // idempotent : rejouer la tâche est sans effet
        }

        $disk = Storage::disk('s3');

        if (! $disk->exists($attachment->object_key)) {
            // L'envoi n'a pas abouti : la pièce reste non servable, et la
            // purge de rétention l'emportera.
            return;
        }

        $bytes = (string) $disk->get($attachment->object_key);

        $hadMetadata = ExifStripper::hasMetadata($bytes);
        $hadLocation = ExifStripper::hasLocation($bytes);

        if ($hadMetadata) {
            // Le nettoyage client a échoué, ou l'envoi n'est pas passé par
            // notre interface. On renettoie plutôt que de rejeter : le
            // signalement d'un document légitime ne doit pas être perdu.
            $bytes = ExifStripper::strip($bytes);
            $disk->put($attachment->object_key, $bytes);
        }

        if (ExifStripper::hasMetadata($bytes)) {
            // Le nettoyage n'a pas suffi : on refuse de marquer servable.
            // Refuser de servir vaut mieux que servir une image non vérifiée.
            Log::warning('Pièce jointe non nettoyable', ['attachment' => $attachment->id]);

            return;
        }

        $attachment->forceFill([
            'exif_stripped_at' => now(),
            'byte_size' => strlen($bytes),
            'content_hash' => hash('sha256', $bytes),
        ])->save();

        if ($hadLocation) {
            // Signal d'exploitation : un nettoyage client défaillant laisse
            // passer des coordonnées GPS et doit être corrigé à la source.
            Log::notice('Coordonnées de géolocalisation retirées côté serveur', [
                'attachment' => $attachment->id,
            ]);
        }
    }
}
