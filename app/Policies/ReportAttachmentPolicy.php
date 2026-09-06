<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReportAttachment;
use App\Models\User;

/**
 * Autorisations sur une image de document.
 *
 * ------------------------------------------------------------------------
 * AUCUN UTILISATEUR NE VOIT JAMAIS UNE IMAGE, À AUCUN NIVEAU, Y COMPRIS N3.
 *
 * Pas même le Trouveur qui l'a envoyée, pas même le Propriétaire après
 * paiement (D-006). L'image est fournie par un tiers sur une personne qui n'a
 * pas consenti ; sa seule fonction utile est la vérification humaine par un
 * administrateur, et le Propriétaire n'a besoin que de savoir où aller.
 * ------------------------------------------------------------------------
 */
final class ReportAttachmentPolicy
{
    public function view(User $user, ReportAttachment $attachment): bool
    {
        // Une pièce dont les métadonnées n'ont pas été retirées côté serveur
        // n'est servie à PERSONNE, administrateur compris (D-029).
        if (! $attachment->isServable()) {
            return false;
        }

        return $user->canAccessSensitiveData();
    }

    /**
     * Générer une URL signée est un acte distinct de la consultation : chaque
     * génération est journalisée individuellement (M-12).
     */
    public function generateSignedUrl(User $user, ReportAttachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }
}
