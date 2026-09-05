<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Limitation de débit (§4.6, docs/PERMISSIONS.md §4).
 *
 * ------------------------------------------------------------------------
 * PRINCIPE DIRECTEUR : l'adresse IP n'est JAMAIS une barrière à elle seule.
 *
 * Le CGNAT des opérateurs mobiles camerounais fait partager une même adresse
 * publique par un grand nombre d'abonnés. Une limitation par IP y est donc
 * simultanément contournable par l'attaquant, qui change d'adresse, et
 * bloquante pour des utilisateurs légitimes qui n'ont rien fait — le pire des
 * deux mondes (THREAT_MODEL.md M-04).
 *
 * Les seuils par IP sont donc LARGES et servent à détecter et corréler. La
 * barrière réelle est le compte, dont le coût d'entrée est la vérification
 * SMS (D-013).
 * ------------------------------------------------------------------------
 */
final class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Connexion : la clé combine identifiant et IP, pour qu'un attaquant
        // ne puisse pas verrouiller le compte d'un tiers en échouant à sa
        // place depuis une autre adresse.
        RateLimiter::for('login', function (Request $request): array {
            $identifier = mb_strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($identifier.'|'.$request->ip()),
                Limit::perMinute(50)->by($request->ip()),
            ];
        });

        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)
            ->by((string) $request->session()->get('login.id')));

        // Inscription : seuil large par IP. Le vrai coût d'entrée est le SMS,
        // pas cette limite.
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(20)
            ->by($request->ip()));

        // Envoi de SMS de vérification : STRICT, par compte et par numéro.
        // Chaque envoi a un coût unitaire réel, et c'est aussi la porte
        // d'entrée que l'on veut rendre chère à un créateur de comptes en
        // masse.
        RateLimiter::for('phone-verification-send', function (Request $request): array {
            $user = $request->user();

            return [
                Limit::perHour(5)->by('user:'.($user->id ?? $request->ip())),
                Limit::perDay(10)->by('user:'.($user->id ?? $request->ip())),
            ];
        });

        // Saisie du code : borne les tentatives de devinette.
        RateLimiter::for('phone-verification-attempt', fn (Request $request): Limit => Limit::perHour(10)
            ->by('user:'.($request->user()->id ?? $request->ip())));

        // Recherche : quota QUOTIDIEN par compte (M-07). La recherche est le
        // point d'attaque principal de la plateforme.
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perDay(
            (int) config('docutrack.limits.searches_per_day')
        )->by('user:'.$request->user()?->id));

        // Signalement : plafond par compte, contre l'injection dans l'index
        // de rapprochement (M-06).
        RateLimiter::for('report', fn (Request $request): Limit => Limit::perDay(
            (int) config('docutrack.limits.reports_per_day')
        )->by('user:'.$request->user()?->id));

        RateLimiter::for('upload', fn (Request $request): Limit => Limit::perHour(30)
            ->by('user:'.$request->user()?->id));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perHour(5)
            ->by(mb_strtolower((string) $request->input('email'))));
    }
}
