<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
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
    expect(DatasetIssue::count())->toBe(0);
});

it('persists dataset issues for a baseline run', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:baseline-issues',
        'name' => 'Baseline Issues',
    ]);

    $payload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 2, 'price' => null, 'status' => 'pending'],
        ['id' => 2, 'price' => -1, 'status' => 'sold'],
        'bad-record',
    ];

    $run = app(PlotDatasetRunService::class)->run($source, $payload);
    $run->refresh();

    expect($run->status)->toBe('baseline');

    $snapshot = DatasetSnapshot::query()->where('source_id', $source->id)->latest('id')->firstOrFail();

    $issues = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->get();
    expect($issues)->toHaveCount(4);

    expect($issues->every(fn ($issue) => $issue->monitored_source_id === $source->id))->toBeTrue();
    expect($issues->every(fn ($issue) => $issue->dataset_snapshot_id === $snapshot->id))->toBeTrue();
    expect($issues->every(fn ($issue) => $issue->dataset_comparison_run_id === $run->id))->toBeTrue();

    expect($issues->where('issue_type', 'invalid_record')->count())->toBe(1);
    expect($issues->where('severity', 'error')->count())->toBe(2); // invalid_record + duplicate_value
    expect($issues->where('severity', 'warning')->count())->toBe(2); // invalid status, invalid price (price not required for pending)
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

it('completes a run even when the current payload has invalid rows; persists issues; compares only comparable records', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:completed-invalid-rows',
        'name' => 'Completed Invalid Rows',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        'bad-record', // should be ignored by comparison, but recorded as an issue
        ['price' => 999, 'status' => 'available'], // missing id (ignored by comparison; issue)
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // comparable: changed
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // comparable: added
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    expect($run2->status)->toBe('completed');
    expect($run2->summary)->toBe([
        'added' => 1,
        'removed' => 0,
        'changed' => 1,
        'unchanged' => 0,
        'added_ids' => [2],
        'removed_ids' => [],
    ]);

    // Comparison + logging should still work for comparable records
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'price')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'status')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 2)->where('field', 'presence')->exists())->toBeTrue();

    // Issues should be detected from the raw current payload and linked to this run.
    // Change-log-driven issues are also created for matched plot field changes.
    $issues = DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->get();
    expect($issues)->toHaveCount(4);
    expect($issues->where('issue_type', 'invalid_record')->count())->toBe(1);
    expect($issues->where('issue_type', 'missing_required_field')->where('field', 'id')->count())->toBe(1);
    expect($issues->where('issue_type', 'plot_status_changed')->count())->toBe(1);
    expect($issues->where('issue_type', 'plot_price_changed')->count())->toBe(1);
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

it('links field-level change logs to the dataset comparison run that produced them', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:run-link',
        'name' => 'Run Link',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 2, 'price' => 200_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // matched changed (price + status)
        ['id' => 3, 'price' => 300_000, 'status' => 'available'], // added (presence)
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);

    expect(ChangeLog::count())->toBe(0);

    $run2 = $service->run($source, $second);

    $logs = ChangeLog::query()->where('entity_type', 'plot')->get();
    expect($logs)->toHaveCount(4);

    expect($logs->every(fn ($log) => (int) $log->dataset_comparison_run_id === (int) $run2->id))->toBeTrue();

    $price = ChangeLog::query()->where('field', 'price')->where('entity_id', 1)->firstOrFail();
    expect((int) $price->dataset_comparison_run_id)->toBe((int) $run2->id);
    expect($price->datasetComparisonRun)->not->toBeNull();
    expect($price->datasetComparisonRun->id)->toBe($run2->id);

    $status = ChangeLog::query()->where('field', 'status')->where('entity_id', 1)->firstOrFail();
    expect((int) $status->dataset_comparison_run_id)->toBe((int) $run2->id);

    $presenceAdded = ChangeLog::query()->where('field', 'presence')->where('entity_id', 3)->firstOrFail();
    expect((int) $presenceAdded->dataset_comparison_run_id)->toBe((int) $run2->id);

    $presenceRemoved = ChangeLog::query()->where('field', 'presence')->where('entity_id', 2)->firstOrFail();
    expect((int) $presenceRemoved->dataset_comparison_run_id)->toBe((int) $run2->id);
});

it('links each runs change logs to its own dataset comparison run id', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:run-link-multi',
        'name' => 'Run Link Multi',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'available'], // price changed
    ];

    $third = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // status changed
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run3 = $service->run($source, $third);

    expect($run2->id)->not->toBe($run3->id);

    $priceLog = ChangeLog::query()->where('field', 'price')->where('entity_id', 1)->firstOrFail();
    expect((int) $priceLog->dataset_comparison_run_id)->toBe((int) $run2->id);

    $statusLog = ChangeLog::query()->where('field', 'status')->where('entity_id', 1)->firstOrFail();
    expect((int) $statusLog->dataset_comparison_run_id)->toBe((int) $run3->id);

    expect(ChangeLog::query()->where('dataset_comparison_run_id', $run2->id)->count())->toBe(1);
    expect(ChangeLog::query()->where('dataset_comparison_run_id', $run3->id)->count())->toBe(1);
});

it('records change logs for newly tracked plot fields without inflating added/removed/unchanged counts or creating new issues', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:newly-tracked-fields',
        'name' => 'Newly Tracked Fields',
    ]);

    $baseline = [
        [
            'id' => 1,
            'price' => 100_000,
            'status' => 'available',
            'title' => 'Plot 12 - The Oakwood',
            'bedrooms' => 3,
            'development' => 'Maple Fields',
            'house_type' => 'Detached',
            'url' => 'https://example.test/plots/12',
        ],
    ];

    $second = [
        [
            'id' => 1,
            'price' => 100_000, // unchanged
            'status' => 'available', // unchanged
            'title' => 'Plot 12 - The Oakwood (Show home)',
            'bedrooms' => 4,
            'development' => 'Maple Fields - Phase 2',
            'house_type' => 'Semi-detached',
            'url' => 'https://example.test/plots/12-renamed',
        ],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    // The matched plot now has tracked-field changes, so it counts as `changed` (not `unchanged`).
    expect($run2->summary)->toBe([
        'added' => 0,
        'removed' => 0,
        'changed' => 1,
        'unchanged' => 0,
        'added_ids' => [],
        'removed_ids' => [],
    ]);

    foreach (['title', 'bedrooms', 'development', 'house_type', 'url'] as $field) {
        expect(ChangeLog::query()
            ->where('entity_type', 'plot')
            ->where('entity_id', 1)
            ->where('field', $field)
            ->where('dataset_comparison_run_id', $run2->id)
            ->exists())->toBeTrue();
    }

    // No price/status changes, no presence changes => no change-log-driven issues.
    expect(DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->count())->toBe(0);
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

