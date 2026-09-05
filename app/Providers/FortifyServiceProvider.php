<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CreatesNewUsers::class, CreateNewUser::class);
    }

    public function boot(): void
    {
        // Fortify est utilisé SANS son interface (D-022) : il fournit la
        // logique, les vues sont les nôtres et utilisent nos composants.
        Fortify::loginView(fn () => View::make('auth.login'));
        Fortify::registerView(fn () => View::make('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => View::make('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => View::make('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => View::make('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => View::make('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => View::make('auth.two-factor-challenge'));
    }
}
