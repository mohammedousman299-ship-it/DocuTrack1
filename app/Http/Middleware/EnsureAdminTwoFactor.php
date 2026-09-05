<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La 2FA est OBLIGATOIRE pour tout administrateur (D-014, D-023).
 *
 * Le compte administrateur est le point de collecte ultime du système : c'est
 * lui qui voit les images de documents (M-09). Un accès administrateur sans
 * second facteur confirmé n'existe pas, même transitoirement.
 *
 * Le refus renvoie 404 et non 403 : un 403 confirmerait l'existence de
 * l'interface d'administration (PERMISSIONS.md §3.3).
 */
final class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Le visiteur anonyme reçoit 404, comme l'utilisateur ordinaire : une
        // redirection vers la connexion confirmerait que la route existe.
        if ($user === null || ! $user->isAdministrator()) {
            abort(404);
        }

        if ($user->isBlocked()) {
            abort(404);
        }

        if (! $user->hasConfirmedTwoFactor()) {
            // L'administrateur est redirigé vers l'activation : il ne s'agit
            // pas d'un intrus, mais d'un compte incomplètement sécurisé.
            return redirect()->route('two-factor.setup')
                ->with('status', __('admin.two_factor_required'));
        }

        return $next($request);
    }
}
