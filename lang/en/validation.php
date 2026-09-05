<?php

declare(strict_types=1);

return [
    'custom' => [
        'phone' => [
            'format' => 'Enter a valid phone number including its country code (for example +237 6 12 34 56 78).',
            'unique' => 'This number is already linked to an account. A number can only be used by one account.',
        ],
    ],

    'attributes' => [
        'full_name' => 'full name',
        'phone' => 'phone number',
        'phone_e164' => 'phone number',
        'email' => 'email address',
        'password' => 'password',
        'code' => 'verification code',
    ],
];
