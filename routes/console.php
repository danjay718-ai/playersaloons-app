<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $output = new \Symfony\Component\Console\Output\ConsoleOutput();
    $output->writeln('<info>' . Inspiring::quote() . '</info>');
})->purpose('Display an inspiring quote');
