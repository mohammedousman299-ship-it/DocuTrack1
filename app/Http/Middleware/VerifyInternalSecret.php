<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protège les endpoints internes déclenchés par cron (§3.1).
 *
 * Sur Vercel il n'existe ni worker permanent ni scheduler en processus :
 * `queue:work` et `schedule:work` ne survivent pas. Le traitement de fond
 * passe donc par des endpoints HTTP, ce qui les rend joignables depuis
 * l'extérieur — d'où ce secret d'en-tête.
 *
 * La comparaison est faite en temps constant : une comparaison naïve
 * permettrait de retrouver le secret octet par octet en mesurant le temps de
 * réponse.
 */
final class VerifyInternalSecret
{
    public const HEADER = 'X-DocuTrack-Internal-Secret';

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('docutrack.internal_cron_secret');
        $provided = (string) $request->header(self::HEADER, '');

        // Un secret vide n'autorise jamais : sans cela, une configuration
        // incomplète ouvrirait les endpoints à tout le monde.
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(404);
        }

        return $next($request);
    }
}
