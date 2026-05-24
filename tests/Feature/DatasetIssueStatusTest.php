<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults new dataset issues to open', function () {
    $issue = DatasetIssue::create([
        'monitored_source_id' => MonitoredSource::create([
            'key' => 'hb:status-default',
            'name' => 'Status Default Source',
        ])->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => DatasetComparisonRun::create([
            'source_id' => MonitoredSource::query()->firstOrFail()->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ])->id,
        'issue_type' => 'status_default_marker',
        'severity' => 'info',
        'message' => 'STATUS_DEFAULT_MARKER',
    ]);

    $issue->refresh();

    expect($issue->status)->toBe(DatasetIssue::STATUS_OPEN);
});

it('starts newly detected issues as open', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:status-detected-open',
        'name' => 'Status Detected Open Source',
    ]);

    $payload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record',
    ];

    $run = app(PlotDatasetRunService::class)->run($source, $payload);

    $issues = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->get();

    expect($issues)->not->toBeEmpty();
    expect($issues->every(fn (DatasetIssue $issue) => $issue->status === DatasetIssue::STATUS_OPEN))->toBeTrue();
});

it('backfills existing rows as open when the status column is added', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:status-migration',
        'name' => 'Status Migration Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'status_migration',
        'severity' => 'warning',
        'message' => 'STATUS_MIGRATION_MARKER',
    ]);

    expect($issue->fresh()->status)->toBe(DatasetIssue::STATUS_OPEN);
});
