<?php

declare(strict_types=1);

namespace App\Search;

use App\Documents\NameConsistencyCheck;
use App\Enums\LostDeclarationStatus;
use App\Enums\NameConsistency;
use App\Models\DocumentType;
use App\Models\LostDeclaration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Enregistrement d'une recherche (D-035).
 *
 * Une seule saisie produit deux choses :
 *   - une DÉCLARATION DE PERTE, conservée pour les signalements futurs ;
 *   - une DEMANDE DE RECHERCHE, traitée en différé, dont le résultat parvient
 *     par notification.
 *
 * L'utilisateur perçoit une recherche ; le système enregistre une déclaration.
 * L'interface doit le dire, sans quoi on crée des déclarations que
 * l'utilisateur ignore avoir faites.
 *
 * La recherche ne rend RIEN en synchrone (D-008) : ni la latence, ni le corps
 * de la réponse ne révèlent le résultat, et la boucle d'énumération rapide
 * disparaît (M-05, M-07).
 */
final class SubmitSearch
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{declaration: LostDeclaration, search_request_id: string}
     */
    public function handle(User $owner, array $input): array
    {
        $combination = SearchCriteria::satisfiedBy($input);

        if ($combination === null) {
            // Une recherche par nom seul est refusée : elle remonterait tous
            // les documents d'un homonyme (§4.2).
            throw new InvalidArgumentException(
                'La combinaison de critères est insuffisante.'
            );
        }

        $type = DocumentType::findOrFail($input['document_type_id']);
        $ownerName = is_string($input['owner_name'] ?? null) ? $input['owner_name'] : null;

        // M-02 : le contrôle de cohérence s'applique ICI, sur le seul chemin
        // menant au niveau N1. C'est la raison principale d'avoir fusionné
        // recherche et déclaration (D-035).
        $consistency = NameConsistencyCheck::compare($owner->full_name, $ownerName);

        return DB::transaction(function () use ($owner, $type, $input, $ownerName, $consistency, $combination): array {
            $declaration = new LostDeclaration([
                'user_id' => $owner->id,
                'document_type_id' => $type->id,
                'owner_name' => $ownerName ?? $owner->full_name,
                'lost_on' => $input['lost_on'] ?? null,
                'lost_region' => $input['lost_region'] ?? null,
                'lost_city' => $input['lost_city'] ?? null,
                'extra_info' => $input['extra_info'] ?? null,
            ]);

            $declaration->setDocumentNumber(
                is_string($input['document_number'] ?? null) ? $input['document_number'] : null
            );
            $declaration->name_consistency = $consistency;
            $declaration->status = $consistency === NameConsistency::Mismatch
                ? LostDeclarationStatus::PendingReview
                : LostDeclarationStatus::Active;
            $declaration->expires_at = now()->addDays($type->retention_days);
            $declaration->save();

            $searchRequestId = (string) Str::uuid();

            // Journalisation exigée par §4.2 : auteur, critères, et plus tard
            // l'ordre de grandeur du résultat — jamais le nombre exact, qui
            // serait un signal d'énumération (M-07).
            DB::table('search_requests')->insert([
                'id' => $searchRequestId,
                'user_id' => $owner->id,
                'document_type_id' => $type->id,
                'owner_name_normalized' => $declaration->owner_name_normalized,
                'number_hmac' => $declaration->number_hmac,
                'region' => $input['lost_region'] ?? null,
                'approximate_lost_on' => $input['lost_on'] ?? null,
                'criteria_combination' => $combination->value,
                'status' => 'queued',
                'ip' => $input['ip'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['declaration' => $declaration, 'search_request_id' => $searchRequestId];
        });
    }
}
