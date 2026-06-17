<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

$providers = [
    AppServiceProvider::class,
    EventServiceProvider::class,
    HorizonServiceProvider::class,
];

// Telescope is a dev dependency — only register when installed
if (class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
    $providers[] = TelescopeServiceProvider::class;
}

return $providers;
