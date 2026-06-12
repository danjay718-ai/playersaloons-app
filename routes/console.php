<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\ConsoleOutput;

Artisan::command('inspire', function () {
    $output = new ConsoleOutput;
    $output->writeln('<info>'.Inspiring::quote().'</info>');
})->purpose('Display an inspiring quote');

use App\Modules\Team\Jobs\ExpireTeamInvitationsJob;
use App\Modules\Tournament\Jobs\AutoCancelTournamentJob;
use App\Modules\Tournament\Jobs\CloseCheckinJob;
use App\Modules\Tournament\Jobs\CloseRegistrationJob;
use App\Modules\Tournament\Jobs\ExpireReservationsJob;
use App\Modules\Tournament\Jobs\OpenCheckinJob;
use App\Modules\Tournament\Jobs\StartTournamentJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CloseRegistrationJob)->everyMinute();
Schedule::job(new OpenCheckinJob)->everyMinute();
Schedule::job(new CloseCheckinJob)->everyMinute();
Schedule::job(new StartTournamentJob)->everyMinute();
Schedule::job(new ExpireReservationsJob)->everyMinute();
Schedule::job(new ExpireTeamInvitationsJob)->everyMinute();

// Assuming AutoCancelTournamentJob doesn't need constructor params anymore
Schedule::job(new AutoCancelTournamentJob)->everyMinute();
