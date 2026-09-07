<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve une route à l'administration SENSIBLE (D-014, PERMISSIONS.md §2.5).
 *
 * La séparation fonctionnel / sensible est préventive : elle n'a d'effet que
 * si les routes qui exposent des noms complets, des numéros ou des images la
 * portent réellement. À placer APRÈS 'admin.2fa', qui garantit déjà le second
 * facteur ; ce middleware ne traite que le cloisonnement des rôles.
 *
 * Le refus est un 404, jamais un 403 : un administrateur fonctionnel n'a pas à
 * apprendre quelles interfaces sensibles existent.
 */
final class EnsureSensitiveAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->canAccessSensitiveData()) {
            abort(404);
        }

        return $next($request);
    }
}
