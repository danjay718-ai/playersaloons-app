<?php

use App\Modules\Match\Jobs\AutoForfeitJob;
use App\Modules\Match\Jobs\ExpireHeadToHeadMatchesJob;
use App\Modules\Stream\Jobs\RefreshProviderLiveStatusesJob;
use App\Modules\Tournament\Jobs\ExpireReservationsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoForfeitJob)->everyMinute()->withoutOverlapping()->onOneServer();
if (config('features.player_wager.enabled')) {
    Schedule::job(new ExpireHeadToHeadMatchesJob)->everyMinute()->withoutOverlapping();
}
Schedule::job(new RefreshProviderLiveStatusesJob)->everyTwoMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new ExpireReservationsJob)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('tournaments:reconcile-lifecycle')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('tournaments:auto-generate')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
