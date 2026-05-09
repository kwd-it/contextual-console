<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contextual-console:run-scheduled-sources')
    ->dailyAt('06:00');

Schedule::command('contextual-console:daily-summary --email')
    ->dailyAt('06:30');
