<?php

declare(strict_types=1);

return [
    'custom' => [
        'phone' => [
            'format' => 'Saisissez un numéro de téléphone valide, avec son indicatif (par exemple +237 6 12 34 56 78).',
            'unique' => 'Ce numéro est déjà associé à un compte. Un numéro ne peut être utilisé que par un seul compte.',
        ],
    ],

    'attributes' => [
        'full_name' => 'nom complet',
        'phone' => 'numéro de téléphone',
        'phone_e164' => 'numéro de téléphone',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'code' => 'code de vérification',
    ],
];
