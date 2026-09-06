<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\FoundReportStatus;
use App\Models\FoundReport;
use App\Models\User;

/**
 * Autorisations sur un signalement (docs/PERMISSIONS.md §2.2).
 *
 * L'autorisation porte sur une RELATION à la ressource, jamais sur un rôle :
 * « peut voir ce signalement » signifie « en est l'auteur », pas « est
 * Trouveur » — cette dernière n'étant pas un rôle mais une capacité (D-004).
 */
final class FoundReportPolicy
{
    /**
     * Le Trouveur ne voit que ses propres signalements, et seulement leurs
     * champs non sensibles.
     *
     * Il n'y voit AUCUN retour de rapprochement : ni statut détaillé, ni
     * compteur. Un tel retour transformerait l'envoi de faux signalements en
     * canal d'extraction (M-06).
     */
    public function view(User $user, FoundReport $report): bool
    {
        return $report->finder_user_id === $user->id
            || $user->canAccessSensitiveData();
    }

    /** Modification possible tant que le signalement n'est pas rapproché. */
    public function update(User $user, FoundReport $report): bool
    {
        return $report->finder_user_id === $user->id
            && $report->status === FoundReportStatus::Active;
    }

    public function delete(User $user, FoundReport $report): bool
    {
        return $this->update($user, $report);
    }

    /**
     * Revendiquer un signalement dont on est l'auteur est interdit.
     *
     * Sinon un compte s'auto-attribuerait un document (D-004). La contrainte
     * existe aussi en base : deux barrières, parce qu'une seule serait une
     * seule.
     */
    public function claim(User $user, FoundReport $report): bool
    {
        return $report->finder_user_id !== $user->id;
    }

    /** Seule la revue sensible accède aux champs de niveau N3. */
    public function viewSensitiveFields(User $user, FoundReport $report): bool
    {
        return $user->canAccessSensitiveData();
    }
}
