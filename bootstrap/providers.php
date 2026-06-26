<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    SettingsServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
];
