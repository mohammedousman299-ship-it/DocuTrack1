<?php

declare(strict_types=1);

namespace App\Documents;

use App\Documents\Upload\UploadTicket;
use App\Jobs\VerifyAttachmentMetadata;
use App\Models\DocumentType;
use App\Models\FoundReport;
use App\Models\ReportAttachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Enregistrement d'un signalement de découverte (§1.5).
 *
 * Concentre les règles qui doivent tenir quel que soit l'écran appelant :
 * contrôle de doublon avant enregistrement, statut initial, rattachement de
 * l'image et déclenchement de sa vérification serveur.
 */
final class SubmitFoundReport
{
    /** @param array<string, mixed> $data */
    public function handle(User $finder, array $data): FoundReport
    {
        $type = DocumentType::findOrFail($data['document_type_id']);

        $objectKey = is_string($data['object_key'] ?? null) ? $data['object_key'] : null;

        // Une clé d'objet n'est acceptée que si ce compte l'a lui-même
        // demandée : sans ce contrôle, on rattacherait à son signalement
        // l'image envoyée par quelqu'un d'autre.
        if ($objectKey !== null && ! UploadTicket::belongsTo($objectKey, $finder)) {
            $objectKey = null;
        }

        $duplicates = DuplicateDetector::existingFor(
            $type->id,
            is_string($data['document_number'] ?? null) ? $data['document_number'] : null,
            is_string($data['owner_name'] ?? null) ? $data['owner_name'] : null,
        );

        $status = DuplicateDetector::statusFor(
            hasDuplicate: $duplicates->isNotEmpty(),
            reporterScore: $finder->reporter_score,
            missingRequiredAttachment: $type->requiresAttachment() && $objectKey === null,
        );

        return DB::transaction(function () use ($finder, $type, $data, $objectKey, $status): FoundReport {
            $report = new FoundReport([
                'finder_user_id' => $finder->id,
                'document_type_id' => $type->id,
                'owner_name' => $data['owner_name'] ?? null,
                'found_on' => $data['found_on'],
                'found_region' => $data['found_region'],
                'found_city' => $data['found_city'],
                'deposit_point_id' => $data['deposit_point_id'] ?? null,
                'deposit_free_text' => $data['deposit_free_text'] ?? null,
                'extra_info' => $data['extra_info'] ?? null,
            ]);

            $report->setDocumentNumber(
                is_string($data['document_number'] ?? null) ? $data['document_number'] : null
            );
            $report->status = $status;
            $report->expires_at = now()->addDays($type->retention_days);
            $report->save();

            if ($objectKey !== null) {
                $attachment = ReportAttachment::create([
                    'found_report_id' => $report->id,
                    'object_key' => $objectKey,
                    'content_hash' => '',
                    'mime_type' => 'image/jpeg',
                    'byte_size' => max(1, Storage::disk('s3')->size($objectKey)),
                    'expires_at' => now()->addDays(
                        (int) config('docutrack.retention.attachment_days')
                    ),
                ]);

                UploadTicket::consume($objectKey);

                // La pièce reste NON SERVABLE tant que cette tâche n'a pas
                // confirmé l'absence de métadonnées (D-029).
                VerifyAttachmentMetadata::dispatch($attachment->id);
            }

            return $report;
        });
    }
}
