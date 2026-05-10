<?php

use Illuminate\Console\Scheduling\Schedule;

it('registers scheduled commands using the configured schedule timezone', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events());

    $tz = config('app.schedule_timezone');

    $runSources = $events->first(fn ($e) => str_contains((string) $e->command, 'run-scheduled-sources'));
    $dailySummary = $events->first(fn ($e) => str_contains((string) $e->command, 'daily-summary'));

    expect($tz)->toBe('Europe/London')
        ->and($runSources)->not->toBeNull()
        ->and($dailySummary)->not->toBeNull()
        ->and($runSources->timezone)->toBe($tz)
        ->and($dailySummary->timezone)->toBe($tz);
});
