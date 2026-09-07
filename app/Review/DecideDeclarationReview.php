<?php

declare(strict_types=1);

namespace App\Review;

use App\Enums\LostDeclarationStatus;
use App\Models\LostDeclaration;
use App\Models\User;
use App\Notifications\NotificationDispatcher;
use App\Notifications\NotificationTemplate;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Décision de revue sur une déclaration à nom incohérent (D-036).
 *
 * Trois propriétés que cette classe garantit :
 *
 *  - un MOTIF est obligatoire, et pour l'approbation comme pour le refus. Un
 *    motif exigé seulement au refus rend l'approbation gratuite, donc le geste
 *    par défaut ; or c'est l'approbation qui ouvre le niveau N1 sur le
 *    document d'un tiers (M-02). C'est elle qui doit être justifiée.
 *  - la décision et sa suite sont ATOMIQUES : une déclaration approuvée dont
 *    la demande de recherche resterait en attente n'aboutirait jamais, et
 *    l'utilisateur attendrait une notification qui ne viendrait pas.
 *  - la trace part au journal APPEND-ONLY, où l'administrateur qui l'a écrite
 *    ne peut ni la modifier ni l'effacer (§4.4).
 */
final class DecideDeclarationReview
{
    /**
     * Un motif doit être une phrase, pas une frappe d'acquittement.
     *
     * Le seuil n'empêche pas d'écrire n'importe quoi — rien ne le peut. Il
     * empêche que « ok » suffise, ce qui rend au moins visible en audit qu'une
     * file a été vidée sans être lue.
     */
    public const MINIMUM_REASON_LENGTH = 15;

    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function handle(
        User $reviewer,
        LostDeclaration $declaration,
        ReviewDecision $decision,
        string $reason,
    ): LostDeclaration {
        if (! $reviewer->canAccessSensitiveData()) {
            // Les noms complets des deux parties sont du niveau N3 : la revue
            // relève de l'administration sensible, jamais fonctionnelle (D-014).
            throw new RuntimeException('Revue réservée à l\'administration sensible.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < self::MINIMUM_REASON_LENGTH) {
            throw new InvalidArgumentException('Le motif de la décision est obligatoire.');
        }

        if (! $declaration->status->awaitsReview()) {
            // Deux relecteurs ouvrant la même file ne doivent pas décider deux
            // fois : la seconde décision écraserait la première sans que
            // personne ne le voie.
            throw new InvalidArgumentException('Cette déclaration a déjà été tranchée.');
        }

        return DB::transaction(function () use ($reviewer, $declaration, $decision, $reason): LostDeclaration {
            $declaration->status = $decision === ReviewDecision::Approve
                ? LostDeclarationStatus::Active
                : LostDeclarationStatus::Rejected;

            $declaration->save();

            // La demande de recherche associée était en attente : rien n'avait
            // été rapproché, rien n'avait été notifié.
            DB::table('search_requests')
                ->where('lost_declaration_id', $declaration->id)
                ->where('status', 'held')
                ->update([
                    // Approuvée, elle repart dans la file normale et sera
                    // traitée au prochain passage du rapprochement.
                    'status' => $decision === ReviewDecision::Approve ? 'queued' : 'failed',
                    'updated_at' => now(),
                ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $reviewer->id,
                'actor_role' => 'admin_sensitive',
                'action' => 'declaration.review_'.$decision->value.'d',
                'entity_type' => 'lost_declaration',
                'entity_id' => $declaration->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            if ($decision === ReviewDecision::Reject) {
                // Un refus silencieux laisserait l'utilisateur attendre une
                // notification qui ne viendrait jamais. Le message ne dit ni
                // le motif ni rien du document : il invite à se connecter.
                $this->dispatcher->queue(
                    recipient: $declaration->user,
                    template: NotificationTemplate::DeclarationRejected,
                    channel: 'sms',
                    idempotencyKey: 'declaration-review:'.$declaration->id,
                );
            }

            return $declaration;
        }, 3);
    }
}
