<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    RateLimitServiceProvider::class,
    FortifyServiceProvider::class,
    NotificationServiceProvider::class,
];
