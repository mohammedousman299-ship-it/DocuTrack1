<?php

declare(strict_types=1);

namespace App\Search;

use App\Documents\DocumentNumber;
use App\Models\FoundReport;
use App\Models\LostDeclaration;
use Illuminate\Support\Collection;

/**
 * Confrontation d'une déclaration aux signalements existants.
 *
 * Version N1 du jalon 4 : elle répond à « existe-t-il un signalement
 * plausible ? », pas « lequel est le meilleur ». Le score pondéré, ses seuils
 * et ses métriques sont du jalon 5 (docs/MATCHING.md).
 *
 * Deux chemins seulement, ceux dont l'index a été validé au jalon 1 :
 *   - égalité exacte du HMAC de numéro ;
 *   - similarité trigramme sur le nom normalisé.
 */
final class SearchMatcher
{
    /**
     * Seuil de similarité de nom pour la présélection.
     *
     * Volontairement bas : il s'agit de ne pas MANQUER un candidat, le tri fin
     * revenant au moteur de score du jalon 5. Un seuil élevé ici produirait
     * des faux négatifs invisibles.
     */
    public const NAME_SIMILARITY_THRESHOLD = 0.35;

    /** @return Collection<int, FoundReport> */
    public function candidatesFor(LostDeclaration $declaration, int $limit = 20): Collection
    {
        $numberHmac = $declaration->number_hmac;
        $name = $declaration->owner_name_normalized;

        return FoundReport::query()
            ->with('documentType')
            // Filtre dur : jamais de correspondance entre types différents.
            ->where('document_type_id', $declaration->document_type_id)
            ->matchable()
            ->where(function ($query) use ($numberHmac, $name): void {
                if ($numberHmac !== null) {
                    $query->orWhere('number_hmac', $numberHmac);
                }

                if ($name !== '') {
                    $query->orWhereRaw(
                        'similarity(owner_name_normalized, ?) >= ?',
                        [$name, self::NAME_SIMILARITY_THRESHOLD]
                    );
                }
            })
            // Une découverte antérieure à la perte est physiquement
            // impossible ; la tolérance couvre une date de perte approximative,
            // qu'on constate souvent plusieurs jours après les faits.
            ->when(
                $declaration->lost_on !== null,
                fn ($q) => $q->where('found_on', '>=', $declaration->lost_on->copy()->subDays(7))
            )
            ->limit($limit)
            ->get();
    }

    /** Empreinte de recherche d'un numéro saisi, sans jamais le conserver. */
    public static function numberHmacFor(?string $input): ?string
    {
        return DocumentNumber::hmacForInput($input);
    }
}
