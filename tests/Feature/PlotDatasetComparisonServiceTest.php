<?php

use App\Core\Models\ChangeLog;
use App\Domains\Housebuilder\Services\ChangeDetectionService;
use App\Domains\Housebuilder\Services\PlotChangeDetector;
use App\Domains\Housebuilder\Services\PlotDatasetComparisonService;
use App\Domains\Housebuilder\Services\PlotDatasetPresenceChangeLogger;
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
        'added_ids' => [4],
        'removed_ids' => [3],
    ]);

    expect(ChangeLog::count())->toBe(1);
    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('price');
});

it('detects and logs a matched plot change in an additional whitelisted field', function () {
    $service = new PlotDatasetComparisonService(
        new PlotChangeDetector(new ChangeDetectionService),
    );

    $before = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 2, 'price' => 200_000, 'status' => 'available'],
    ];

    $after = [
        ['id' => 1, 'price' => 100_000, 'status' => 'reserved'], // status changed
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // unchanged
    ];

    $summary = $service->compare($before, $after);

    expect($summary)->toBe([
        'added' => 0,
        'removed' => 0,
        'changed' => 1,
        'unchanged' => 1,
        'added_ids' => [],
        'removed_ids' => [],
    ]);

    expect(ChangeLog::count())->toBe(1);
    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('status');
    expect($log->old_value)->toBe('available');
    expect($log->new_value)->toBe('reserved');
});

it('logs multiple matched field changes for the same plot as multiple change log entries', function () {
    $service = new PlotDatasetComparisonService(
        new PlotChangeDetector(new ChangeDetectionService),
    );

    $before = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $after = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ];

    $summary = $service->compare($before, $after);

    expect($summary['changed'])->toBe(1);
    expect($summary['unchanged'])->toBe(0);

    expect(ChangeLog::count())->toBe(2);
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'price')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'status')->exists())->toBeTrue();
});

it('logs added and removed plots as presence changes', function () {
    $comparison = [
        'added_ids' => [123],
        'removed_ids' => [456],
    ];

    $logger = new PlotDatasetPresenceChangeLogger(new ChangeDetectionService);
    $logger->logFromComparison($comparison);

    expect(ChangeLog::count())->toBe(2);

    $added = ChangeLog::query()->where('entity_id', 123)->firstOrFail();
    expect($added->entity_type)->toBe('plot');
    expect((int) $added->entity_id)->toBe(123);
    expect($added->field)->toBe('presence');
    expect($added->old_value)->toBeNull();
    expect($added->new_value)->toBe('present');

    $removed = ChangeLog::query()->where('entity_id', 456)->firstOrFail();
    expect($removed->entity_type)->toBe('plot');
    expect((int) $removed->entity_id)->toBe(456);
    expect($removed->field)->toBe('presence');
    expect($removed->old_value)->toBe('present');
    expect($removed->new_value)->toBeNull();
});

it('handles mixed datasets without false positives (price-change + added + removed + unchanged)', function () {
    $changeDetection = new ChangeDetectionService;
    $comparisonService = new PlotDatasetComparisonService(
        new PlotChangeDetector($changeDetection),
    );
    $presenceLogger = new PlotDatasetPresenceChangeLogger($changeDetection);

    $before = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'], // will change
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // unchanged
        ['id' => 3, 'price' => 300_000, 'status' => 'available'], // removed
    ];

    $after = [
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // unchanged (reordered)
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed (multiple fields)
        ['id' => 4, 'price' => 400_000, 'status' => 'available'], // added
    ];

    $summary = $comparisonService->compare($before, $after);
    $presenceLogger->logFromComparison($summary);

    expect($summary)->toBe([
        'added' => 1,
        'removed' => 1,
        'changed' => 1,
        'unchanged' => 1,
        'added_ids' => [4],
        'removed_ids' => [3],
    ]);

    // 2 matched-field logs (price + status) + 2 presence logs (added + removed)
    expect(ChangeLog::count())->toBe(4);

    $price = ChangeLog::query()->where('field', 'price')->firstOrFail();
    expect($price->entity_type)->toBe('plot');
    expect((int) $price->entity_id)->toBe(1);

    $status = ChangeLog::query()->where('field', 'status')->firstOrFail();
    expect($status->entity_type)->toBe('plot');
    expect((int) $status->entity_id)->toBe(1);

    $added = ChangeLog::query()->where('field', 'presence')->where('entity_id', 4)->firstOrFail();
    expect($added->entity_type)->toBe('plot');
    expect($added->old_value)->toBeNull();
    expect($added->new_value)->toBe('present');

    $removed = ChangeLog::query()->where('field', 'presence')->where('entity_id', 3)->firstOrFail();
    expect($removed->entity_type)->toBe('plot');
    expect($removed->old_value)->toBe('present');
    expect($removed->new_value)->toBeNull();
});

it('does not log added/removed presence when datasets are identical (including reorder)', function () {
    $comparisonService = new PlotDatasetComparisonService(
        new PlotChangeDetector(new ChangeDetectionService),
    );
    $presenceLogger = new PlotDatasetPresenceChangeLogger(new ChangeDetectionService);

    $before = [
        ['id' => 1, 'price' => 100_000],
        ['id' => 2, 'price' => 200_000],
    ];

    $after = [
        ['id' => 2, 'price' => 200_000], // reorder only
        ['id' => 1, 'price' => 100_000],
    ];

    $summary = $comparisonService->compare($before, $after);
    $presenceLogger->logFromComparison($summary);

    expect($summary['added'])->toBe(0);
    expect($summary['removed'])->toBe(0);
    expect($summary['added_ids'])->toBe([]);
    expect($summary['removed_ids'])->toBe([]);

    expect(ChangeLog::count())->toBe(0);
});
