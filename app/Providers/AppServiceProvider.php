<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::use(Carbon::class);

        /*
         | Politique de mot de passe (§4.6), définie en un seul endroit.
         |
         | uncompromised() interroge un service externe pour savoir si le mot de
         | passe figure dans une fuite connue. L'échange respecte la
         | k-anonymité — seuls les cinq premiers caractères de l'empreinte
         | SHA-1 sont transmis, jamais le mot de passe — mais c'est un appel
         | sortant : il est désactivé en test, où il ralentirait la suite et la
         | rendrait dépendante du réseau.
         */
        Password::defaults(function (): Password {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->runningUnitTests() ? $rule : $rule->uncompromised();
        });
    }
}
