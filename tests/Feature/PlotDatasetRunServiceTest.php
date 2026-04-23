<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a baseline run for the first snapshot without change logging', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:test-1',
        'name' => 'Test Source 1',
    ]);

    $payload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $run = app(PlotDatasetRunService::class)->run($source, $payload);

    expect(DatasetSnapshot::query()->where('source_id', $source->id)->count())->toBe(1);
    expect(DatasetComparisonRun::query()->where('source_id', $source->id)->count())->toBe(1);

    $run->refresh();
    expect($run->status)->toBe('baseline');
    expect($run->previous_snapshot_id)->toBeNull();
    expect($run->summary)->toBeNull();

    expect(ChangeLog::count())->toBe(0);
});

it('creates a completed run for a second snapshot and persists the comparison summary + logs', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:test-2',
        'name' => 'Test Source 2',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // matched plot changed (2 fields)
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // added
    ];

    app(PlotDatasetRunService::class)->run($source, $baseline);
    $run2 = app(PlotDatasetRunService::class)->run($source, $second);

    expect(DatasetSnapshot::query()->where('source_id', $source->id)->count())->toBe(2);
    expect(DatasetComparisonRun::query()->where('source_id', $source->id)->count())->toBe(2);

    $latestRun = DatasetComparisonRun::query()->where('source_id', $source->id)->latest('id')->firstOrFail();
    expect($latestRun->id)->toBe($run2->id);
    expect($latestRun->status)->toBe('completed');
    expect($latestRun->previous_snapshot_id)->not->toBeNull();

    expect($latestRun->summary)->toBe([
        'added' => 1,
        'removed' => 0,
        'changed' => 1,
        'unchanged' => 0,
        'added_ids' => [2],
        'removed_ids' => [],
    ]);

    // Matched-field logs (price + status) + presence log for added plot
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'price')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'status')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 2)->where('field', 'presence')->exists())->toBeTrue();
});

it('isolates runs per source (second run compares only against that sources prior snapshot)', function () {
    $sourceA = MonitoredSource::create(['key' => 'hb:iso-a', 'name' => 'Iso A']);
    $sourceB = MonitoredSource::create(['key' => 'hb:iso-b', 'name' => 'Iso B']);

    $a1 = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];
    $b1 = [
        ['id' => 99, 'price' => 900_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($sourceA, $a1);
    $service->run($sourceB, $b1);

    $a2 = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'], // unchanged
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // added
    ];
    $b2 = [
        ['id' => 99, 'price' => 910_000, 'status' => 'reserved'], // changed
    ];

    $aRun2 = $service->run($sourceA, $a2);
    $bRun2 = $service->run($sourceB, $b2);

    expect(DatasetSnapshot::query()->where('source_id', $sourceA->id)->count())->toBe(2);
    expect(DatasetSnapshot::query()->where('source_id', $sourceB->id)->count())->toBe(2);
    expect(DatasetComparisonRun::query()->where('source_id', $sourceA->id)->count())->toBe(2);
    expect(DatasetComparisonRun::query()->where('source_id', $sourceB->id)->count())->toBe(2);

    $aPrevSnapshotId = DatasetSnapshot::query()->where('source_id', $sourceA->id)->oldest('id')->value('id');
    $bPrevSnapshotId = DatasetSnapshot::query()->where('source_id', $sourceB->id)->oldest('id')->value('id');

    expect($aRun2->previous_snapshot_id)->toBe($aPrevSnapshotId);
    expect($bRun2->previous_snapshot_id)->toBe($bPrevSnapshotId);
    expect($aRun2->previous_snapshot_id)->not->toBe($bRun2->previous_snapshot_id);

    expect($aRun2->summary)->toBe([
        'added' => 1,
        'removed' => 0,
        'changed' => 0,
        'unchanged' => 1,
        'added_ids' => [2],
        'removed_ids' => [],
    ]);

    expect($bRun2->summary)->toBe([
        'added' => 0,
        'removed' => 0,
        'changed' => 1,
        'unchanged' => 0,
        'added_ids' => [],
        'removed_ids' => [],
    ]);
});

it('persists a summary that exactly matches the comparison output fields', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:test-summary',
        'name' => 'Summary Source',
    ]);

    $before = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'], // will change
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // removed
    ];

    $after = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed
        ['id' => 3, 'price' => 300_000, 'status' => 'available'], // added
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $before);
    $run2 = $service->run($source, $after);

    $expected = [
        'added' => 1,
        'removed' => 1,
        'changed' => 1,
        'unchanged' => 0,
        'added_ids' => [3],
        'removed_ids' => [2],
    ];

    $run2->refresh();
    expect($run2->summary)->toBe($expected);

    $persisted = DatasetComparisonRun::query()->findOrFail($run2->id);
    expect($persisted->summary)->toBe($expected);
});

