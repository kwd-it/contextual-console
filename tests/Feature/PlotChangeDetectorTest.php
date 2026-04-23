<?php

use App\Core\Models\ChangeLog;
use App\Domains\Housebuilder\Services\ChangeDetectionService;
use App\Domains\Housebuilder\Services\PlotChangeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes a change log when plot price changes', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000],
        ['id' => 1, 'price' => 110_000],
    );

    expect($logged)->toBe(1);
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

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000],
        ['id' => 1, 'price' => 100_000],
    );

    expect($logged)->toBe(0);
    expect(ChangeLog::count())->toBe(0);
});

it('writes a change log when a whitelisted non-price field changes', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 1, 'price' => 100_000, 'status' => 'reserved'],
    );

    expect($logged)->toBe(1);
    expect(ChangeLog::count())->toBe(1);

    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('status');
    expect($log->old_value)->toBe('available');
    expect($log->new_value)->toBe('reserved');
});

it('logs multiple field changes on the same matched plot as multiple change log entries', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    );

    expect($logged)->toBe(2);
    expect(ChangeLog::count())->toBe(2);

    $price = ChangeLog::query()->where('field', 'price')->firstOrFail();
    expect($price->entity_type)->toBe('plot');
    expect((int) $price->entity_id)->toBe(1);

    $status = ChangeLog::query()->where('field', 'status')->firstOrFail();
    expect($status->entity_type)->toBe('plot');
    expect((int) $status->entity_id)->toBe(1);
});
