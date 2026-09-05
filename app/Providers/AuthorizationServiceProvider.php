<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Gates transverses (docs/PERMISSIONS.md §3.1).
 *
 * Les Gates ne portent QUE les décisions qui ne dépendent pas d'une ressource.
 * Tout ce qui porte sur une ressource — « peut voir le N2 de ce signalement »
 * — relève d'une Policy, parce que l'autorisation réelle est une relation à
 * une ressource et non un rôle (D-004).
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Administration fonctionnelle : utilisateurs, catégories, retours,
        // tableaux de bord. Ne donne JAMAIS accès aux noms complets, aux
        // numéros ni aux images (D-014).
        Gate::define('admin.functional', fn (User $user): bool => $user->canAdministerFunctionally());

        // Revue sensible : images, noms complets, numéros, éléments de preuve.
        // Chaque accès exige en outre la saisie d'un motif, appliquée au
        // niveau de l'écran.
        Gate::define('admin.sensitive', fn (User $user): bool => $user->canAccessSensitiveData());

        /*
         | Recherche.
         |
         | Trois conditions cumulatives, et l'administrateur en est
         | volontairement exclu : la recherche est l'outil d'extraction du
         | système, et un administrateur dispose déjà d'un accès direct et
         | tracé. Lui laisser la recherche créerait un chemin moins tracé que
         | les autres (PERMISSIONS.md §2.3).
         */
        Gate::define('search.perform', fn (User $user): bool => ! $user->isAdministrator()
            && $user->hasVerifiedPhone()
            && ! $user->isBlocked());
    }
}
