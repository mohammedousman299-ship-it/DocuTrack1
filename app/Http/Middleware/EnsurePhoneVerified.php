<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un numéro de téléphone vérifié.
 *
 * Sans lui, les quotas par compte seraient décoratifs : créer un compte ne
 * coûterait qu'une adresse e-mail jetable (D-013, M-04). Ce middleware garde
 * la recherche, la revendication et le signalement.
 */
final class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($user->isBlocked()) {
            abort(403);
        }

        if (! $user->hasVerifiedPhone()) {
            return redirect()->route('phone.verify.show');
        }

        return $next($request);
    }
}
