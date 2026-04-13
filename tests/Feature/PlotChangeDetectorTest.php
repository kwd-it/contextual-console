<?php

use App\Core\Models\ChangeLog;
use App\Domains\Housebuilder\Services\ChangeDetectionService;
use App\Domains\Housebuilder\Services\PlotChangeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes a change log when plot price changes', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $detector->detect(
        ['id' => 1, 'price' => 100_000],
        ['id' => 1, 'price' => 110_000],
    );

    expect(ChangeLog::count())->toBe(1);

    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('price');
    expect($log->old_value)->toBe('100000');
    expect($log->new_value)->toBe('110000');
});

it('does nothing when plot price is unchanged', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $detector->detect(
        ['id' => 1, 'price' => 100_000],
        ['id' => 1, 'price' => 100_000],
    );

    expect(ChangeLog::count())->toBe(0);
});
