<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Rôles d'administration (D-014).
 *
 * La séparation entre administration fonctionnelle et revue sensible est un
 * contrôle PRÉVENTIF, là où la seule journalisation n'est que détective : un
 * administrateur fonctionnel ne voit jamais un nom complet, un numéro ni une
 * image de document (docs/PERMISSIONS.md §2.5).
 *
 * « Propriétaire » et « Trouveur » n'apparaissent pas ici : ce sont des
 * capacités contextuelles, pas des rôles (D-004).
 */
enum AdminRole: string
{
    case None = 'none';
    case Functional = 'functional';
    case Sensitive = 'sensitive';
    case Both = 'both';

    /** Gestion des utilisateurs, catégories, retours, tableaux de bord. */
    public function grantsFunctional(): bool
    {
        return $this === self::Functional || $this === self::Both;
    }

    /** Accès aux noms complets, numéros, images et éléments de preuve. */
    public function grantsSensitive(): bool
    {
        return $this === self::Sensitive || $this === self::Both;
    }

    public function isAdministrator(): bool
    {
        return $this !== self::None;
    }
}
