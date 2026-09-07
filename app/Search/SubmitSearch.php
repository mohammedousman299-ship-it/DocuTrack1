<?php

declare(strict_types=1);

namespace App\Search;

use App\Documents\NameConsistencyCheck;
use App\Enums\LostDeclarationStatus;
use App\Enums\NameConsistency;
use App\Models\DocumentType;
use App\Models\LostDeclaration;
use App\Models\User;
use App\Search\Abuse\ApplyEnumerationResponse;
use Illuminate\Support\Carbon;
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
    public function __construct(private readonly ApplyEnumerationResponse $abuseResponse) {}

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

        $this->assertWithinDailyQuota($owner);

        // Détection APRÈS enregistrement de la recherche précédente, avant
        // celle-ci : un compte déjà bloqué n'atteint pas ce point, le
        // middleware l'ayant écarté.
        $this->abuseResponse->handle($owner);

        if ($owner->fresh()?->isBlocked() === true) {
            throw new SearchNotAllowed(
                'Trop de recherches en peu de temps. Réessayez plus tard.'
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
                // Lien EXPLICITE : le traitement doit savoir de quelle
                // déclaration relève la demande, à la fois pour ne pas se
                // tromper de déclaration et pour voir si elle est en revue.
                'lost_declaration_id' => $declaration->id,
                'document_type_id' => $type->id,
                'owner_name_normalized' => $declaration->owner_name_normalized,
                'number_hmac' => $declaration->number_hmac,
                'region' => $input['lost_region'] ?? null,
                'approximate_lost_on' => $input['lost_on'] ?? null,
                'criteria_combination' => $combination->value,
                // Une déclaration en revue ne produit RIEN tant qu'un humain
                // n'a pas tranché : ni rapprochement, ni notification, ni
                // affichage N1 (D-036). Sans cette attente, la mise en revue
                // n'aurait bloqué que la notification, pas la divulgation.
                'status' => $declaration->status === LostDeclarationStatus::PendingReview
                    ? 'held'
                    : 'queued',
                'ip' => $input['ip'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['declaration' => $declaration, 'search_request_id' => $searchRequestId];
        }, 3);
    }

    /**
     * Quota QUOTIDIEN par compte (§4.2, M-07).
     *
     * Compté sur `search_requests`, pas sur le cache : un vidage de cache ne
     * doit pas remettre le compteur à zéro, et la table porte de toute façon
     * la trace exigée par la §4.2. L'index (user_id, created_at) existe.
     *
     * Fenêtre GLISSANTE de 24 heures, et non journée civile : celle-ci
     * autoriserait deux fois le quota à cheval sur minuit.
     *
     * Le limiteur nommé 'search' reste déclaré pour d'éventuelles routes HTTP,
     * mais il ne suffirait pas ici : la recherche est soumise par Livewire,
     * dont les requêtes passent toutes par la même route. Le quota appartient
     * au domaine, pas au transport.
     */
    private function assertWithinDailyQuota(User $owner): void
    {
        $quota = (int) config('docutrack.limits.searches_per_day');

        $used = DB::table('search_requests')
            ->where('user_id', $owner->id)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        if ($used >= $quota) {
            throw new SearchNotAllowed(
                'Vous avez atteint le nombre de recherches autorisé pour aujourd\'hui. '
                .'Vos déclarations restent actives : vous serez prévenu si un signalement leur correspond.'
            );
        }
    }
}
