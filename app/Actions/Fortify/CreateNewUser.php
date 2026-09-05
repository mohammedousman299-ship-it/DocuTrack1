<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Auth\PhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Création de compte.
 *
 * Un seul type de compte (D-004). Le numéro de téléphone est obligatoire et
 * unique : c'est le contrôle anti-Sybil principal (D-013), sans lequel les
 * quotas par compte seraient décoratifs.
 *
 * Le compte est créé SANS téléphone vérifié : la vérification est un parcours
 * distinct, et tant qu'elle n'a pas abouti le compte ne peut ni rechercher ni
 * revendiquer.
 */
final class CreateNewUser implements CreatesNewUsers
{
    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        $input['phone_e164'] = PhoneNumber::normalize(
            is_string($input['phone'] ?? null) ? $input['phone'] : null
        );

        Validator::make($input, [
            'full_name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'phone_e164' => [
                'required', 'string',
                // Validation structurelle seulement : les formats exacts des
                // numéros camerounais ne sont pas connus (voir PhoneNumber).
                'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique(User::class, 'phone_e164'),
            ],
            'password' => [
                'required', 'string', 'confirmed',
                // Politique définie une seule fois, dans AppServiceProvider.
                Password::defaults(),
            ],
        ], [
            'phone_e164.regex' => __('validation.custom.phone.format'),
            'phone_e164.unique' => __('validation.custom.phone.unique'),
        ], [
            'phone_e164' => __('validation.attributes.phone'),
        ])->validate();

        return User::create([
            'full_name' => $input['full_name'],
            'email' => $input['email'],
            'phone_e164' => $input['phone_e164'],
            'password' => $input['password'],
            'locale' => app()->getLocale(),
        ]);
    }
}
