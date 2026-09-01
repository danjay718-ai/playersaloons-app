<?php

use App\Modules\Match\Jobs\AutoForfeitJob;
use App\Modules\Match\Jobs\ExpireHeadToHeadMatchesJob;
use App\Modules\Match\Jobs\ReconcileMatchReadinessJob;
use App\Modules\Match\Jobs\ResolveV2ResultTimeoutsJob;
use App\Modules\Match\Jobs\ResolveV2StalledMatchesJob;
use App\Modules\Stream\Jobs\RefreshProviderLiveStatusesJob;
use App\Modules\Tournament\Jobs\ExpireReservationsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoForfeitJob)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new ReconcileMatchReadinessJob)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new ResolveV2ResultTimeoutsJob)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new ResolveV2StalledMatchesJob)->everyMinute()->withoutOverlapping()->onOneServer();
if (config('features.player_wager.enabled')) {
    Schedule::job(new ExpireHeadToHeadMatchesJob)->everyMinute()->withoutOverlapping();
}
Schedule::job(new RefreshProviderLiveStatusesJob)->everyTwoMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new ExpireReservationsJob)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('tournaments:reconcile-lifecycle')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('tournaments:auto-generate')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('tournaments:purge-empty-v2')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('errors:prune --days=90')->dailyAt('03:30')->onOneServer();
