<?php

use App\Modules\Match\Jobs\AutoForfeitJob;
use App\Modules\Match\Jobs\ExpireHeadToHeadMatchesJob;
use App\Modules\Stream\Jobs\RefreshProviderLiveStatusesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoForfeitJob)->everyMinute();
Schedule::job(new ExpireHeadToHeadMatchesJob)->everyMinute();
Schedule::job(new RefreshProviderLiveStatusesJob)->everyTwoMinutes()->withoutOverlapping();
Schedule::command('tournaments:auto-cancel')->everyMinute()->withoutOverlapping();
Schedule::command('tournaments:auto-generate')->hourly()->withoutOverlapping();
