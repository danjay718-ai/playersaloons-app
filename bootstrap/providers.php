<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
];
