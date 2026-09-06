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

        $response->headers->set('Content-Security-Policy', $this->policy($nonce, $request->secure()));
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

    /**
     * Origine du stockage objet, si elle diffère de l'application.
     *
     * Dérivée de la configuration plutôt qu'écrite en dur : l'origine change
     * entre développement et production, et une CSP qui ne suit pas casserait
     * l'envoi sans avertissement.
     */
    private function storageOrigin(): ?string
    {
        $endpoint = (string) config('filesystems.disks.s3.endpoint');

        if ($endpoint === '') {
            return null;
        }

        $parts = parse_url($endpoint);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }

    private function policy(string $nonce, bool $secure): string
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
            // L'envoi direct écrit dans le stockage objet depuis le navigateur
            // (§3.3) : sans son origine ici, la requête est bloquée et le
            // parcours Trouveur s'arrête sans message d'erreur exploitable.
            // C'est exactement le genre de fonctionnalité qu'une CSP casse.
            'connect-src '.implode(' ', array_filter(["'self'", $this->storageOrigin()])),
            "media-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        // upgrade-insecure-requests réécrit en HTTPS toute requête http:// de
        // la page, y compris vers d'autres origines. Sur une installation
        // locale servie en clair, cela casse l'envoi direct vers le stockage
        // objet — le navigateur tente https:// sur un service qui n'écoute
        // qu'en http, sans message d'erreur exploitable. La directive n'a de
        // sens qu'une fois la page elle-même servie en HTTPS.
        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
