<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'home' => '/tableau-de-bord',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],

    /*
     | Limiteurs de débit (§4.6). Détail dans RateLimitServiceProvider.
     |
     | L'IP n'est jamais une barrière à elle seule : le CGNAT des opérateurs
     | mobiles camerounais la rend contournable par l'attaquant ET bloquante
     | pour des utilisateurs légitimes (THREAT_MODEL.md M-04).
     */
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'confirm-password' => null,
    ],

    'lowercase_usernames' => true,
    'views' => true,

    'features' => [
        Features::registration(),
        Features::resetPasswords(),

        // Activée : l'adresse e-mail est le canal de récupération de compte et
        // le second canal de notification (§7).
        Features::emailVerification(),

        Features::updateProfileInformation(),
        Features::updatePasswords(),

        /*
         | 2FA par TOTP, OBLIGATOIRE pour l'administrateur (D-014, D-023).
         |
         | Le SMS est volontairement écarté pour ce rôle : il est vulnérable au
         | SIM swap (M-13), et c'est le compte administrateur qui voit toutes
         | les images de documents (M-09). Les codes de récupération répondent
         | au besoin de repli sans réintroduire ce canal.
         */
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),

        // Passkeys écartées au jalon 2 : elles ajoutent une surface et un
        // parcours à concevoir, sur une population majoritairement mobile dont
        // le taux de support n'est pas connu. À reconsidérer plus tard.
    ],
];
