<?php

use App\Core\Models\ChangeLog;
use App\Domains\Housebuilder\Services\ChangeDetectionService;
use App\Domains\Housebuilder\Services\PlotChangeDetector;
use App\Domains\Housebuilder\Services\PlotDatasetComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('summarises added, removed, unchanged, and price-changed plots', function () {
    $service = new PlotDatasetComparisonService(
        new PlotChangeDetector(new ChangeDetectionService),
    );

    $before = [
        ['id' => 1, 'price' => 100_000],
        ['id' => 2, 'price' => 200_000],
        ['id' => 3, 'price' => 300_000],
    ];

    $after = [
        ['id' => 1, 'price' => 110_000],
        ['id' => 2, 'price' => 200_000],
        ['id' => 4, 'price' => 400_000],
    ];

    $summary = $service->compare($before, $after);

    expect($summary)->toBe([
        'added' => 1,
        'removed' => 1,
        'changed' => 1,
        'unchanged' => 1,
    ]);

    expect(ChangeLog::count())->toBe(1);
    $log = ChangeLog::first();
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('price');
});
