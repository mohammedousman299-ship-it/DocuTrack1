<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP (§4.6).
 *
 * Posés au jalon 2 et non au jalon 8 (D-025) : une CSP compatible Livewire et
 * Alpine s'établit bien plus facilement sur trois écrans que sur trente, où
 * chaque violation serait immédiatement rattachable au composant fautif.
 * Ajoutée tardivement, elle casserait des composants écrits entre-temps et la
 * pression serait d'assouplir la politique plutôt que de corriger le code.
 *
 * Le nonce est régénéré à chaque réponse et exposé aux vues.
 */
final class SecurityHeaders
{
    public const NONCE_ATTRIBUTE = 'csp_nonce';

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);
        $request->attributes->set(self::NONCE_ATTRIBUTE, $nonce);

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), geolocation=(), microphone=(), payment=(), usb=()'
        );
        $response->headers->set('X-Frame-Options', 'DENY');

        // HSTS n'a de sens que sur HTTPS ; l'émettre en clair n'apporte rien et
        // pourrait rendre un environnement local inaccessible.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $response->headers->remove('X-Powered-By');

        return $response;
    }

    private function policy(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // 'unsafe-inline' sur les styles : Blade et Tailwind produisent des
            // attributs style ponctuels (barres de progression, squelettes).
            // C'est un assouplissement RÉEL, limité aux styles, qui n'ouvre pas
            // l'exécution de code. Le durcir demanderait de supprimer tout
            // attribut style du projet — à réévaluer au jalon 8.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            // Les images de documents transitent par le stockage objet, dont
            // l'origine devra être ajoutée ici au jalon 3.
            "connect-src 'self'",
            "media-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ];

        return implode('; ', $directives);
    }
}
