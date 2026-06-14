<?php

use Illuminate\Support\Facades\Schedule;
use App\Modules\Match\Jobs\AutoForfeitJob;

Schedule::job(new AutoForfeitJob)->everyMinute();
