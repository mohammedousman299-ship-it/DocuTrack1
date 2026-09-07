<?php

declare(strict_types=1);

namespace App\Review;

use App\Models\LostDeclaration;

/**
 * Ce qu'un relecteur voit d'une déclaration en revue — et rien d'autre.
 *
 * Le principe de DISCLOSURE_LEVELS.md §4.1 vaut aussi pour l'administration :
 * la vue ne reçoit pas de modèle Eloquent, elle reçoit une projection dont les
 * champs interdits sont ABSENTS, pas masqués à l'affichage.
 *
 * Ce qui est délibérément exclu, et pourquoi :
 *
 *  - le NUMÉRO du document, même partiel. La question posée au relecteur est
 *    « ces deux noms désignent-ils plausiblement une déclaration faite pour un
 *    proche ? ». Le numéro n'y répond pas. L'afficher ferait de chaque revue
 *    une consultation de donnée N3 sans nécessité (M-09).
 *  - la VILLE de perte. La région suffirait déjà à situer ; la ville ne sert
 *    pas la décision.
 *  - les CORRESPONDANCES éventuelles. Le relecteur ne doit pas savoir si la
 *    déclaration « tomberait juste » : ce serait décider en connaissant la
 *    récompense, exactement la pression que la revue existe pour éviter.
 *
 * Ce qui est inclus et ne l'était pas dans l'idée d'origine : `explanation`,
 * le texte libre saisi par l'utilisateur. D-036 a écarté la case « je déclare
 * pour un proche » parce qu'un attaquant la coche aussi. Une phrase écrite à
 * la main ne prouve rien non plus, mais elle donne au relecteur la SEULE
 * matière sur laquelle exercer un jugement. Sans elle, la file demande de
 * trancher sur deux noms nus, et la décision devient arbitraire.
 */
final readonly class DeclarationReviewItem
{
    private function __construct(
        public string $id,
        public string $documentTypeLabel,
        public string $accountName,
        public string $declaredName,
        public ?string $explanation,
        public string $submittedOn,
    ) {}

    public static function from(LostDeclaration $declaration, ?string $locale = null): self
    {
        $explanation = is_string($declaration->extra_info)
            ? trim($declaration->extra_info)
            : null;

        return new self(
            id: $declaration->id,
            documentTypeLabel: $declaration->documentType->label($locale),
            accountName: $declaration->user->full_name,
            declaredName: $declaration->owner_name,
            explanation: $explanation === '' ? null : $explanation,
            submittedOn: $declaration->created_at?->translatedFormat('j F Y') ?? '—',
        );
    }
}
