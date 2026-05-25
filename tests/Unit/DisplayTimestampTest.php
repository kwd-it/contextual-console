<?php

use App\Support\DisplayTimestamp;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

it('formats stored UTC timestamps in the configured schedule timezone', function () {
    config(['app.schedule_timezone' => 'Europe/London']);

    $utc = Carbon::parse('2026-05-14 05:42:00', 'UTC');

    expect(DisplayTimestamp::format($utc))->toBe('2026-05-14 06:42:00');
});

it('returns a placeholder for null timestamps', function () {
    expect(DisplayTimestamp::format(null))->toBe('-');
    expect(DisplayTimestamp::format(null, 'n/a'))->toBe('n/a');
});

it('does not use mojibake em dash placeholders', function () {
    expect(DisplayTimestamp::format(null))->not->toContain('ÔÇö');
});
