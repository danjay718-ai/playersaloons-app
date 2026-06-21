<?php

use App\Modules\Match\Jobs\AutoForfeitJob;
use App\Modules\Match\Jobs\ExpireHeadToHeadMatchesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new AutoForfeitJob)->everyMinute();
Schedule::job(new ExpireHeadToHeadMatchesJob)->everyMinute();
