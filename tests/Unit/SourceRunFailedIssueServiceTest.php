<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('excludes recovered source_run_failed issues from the active scope', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:recovered-run-failed-issue',
        'name' => 'Recovered Run Failed Issue Source',
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $failedRun->id,
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'severity' => 'error',
        'message' => 'SOURCE_RUN_FAILED_RECOVERED',
    ]);

    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    expect(DatasetIssue::query()->active()->count())->toBe(0);
    expect(DatasetIssue::query()->where('issue_type', SourceRunFailedIssueService::ISSUE_TYPE)->count())->toBe(1);
});

it('keeps the latest source_run_failed issue active when the source is still failing', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:active-run-failed-issue',
        'name' => 'Active Run Failed Issue Source',
    ]);

    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subDays(2),
        'finished_at' => now()->subDays(2),
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $failedRun->id,
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'severity' => 'error',
        'message' => 'SOURCE_RUN_FAILED_CURRENT',
    ]);

    expect(DatasetIssue::query()->active()->count())->toBe(1);
    expect(DatasetIssue::query()->active()->first()?->message)->toBe('SOURCE_RUN_FAILED_CURRENT');
});

it('builds an active scope that treats null issue_type as active', function () {
    $sql = strtolower(DatasetIssue::query()->active()->toSql());

    expect($sql)->toContain('"issue_type" is null')
        ->and($sql)->toContain('"issue_type" !=');
});

it('marks superseded source_run_failed issues resolved when a later run completes', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:resolve-run-failed-issue',
        'name' => 'Resolve Run Failed Issue Source',
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay(),
    ]);

    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $failedRun->id,
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'severity' => 'error',
        'message' => 'SOURCE_RUN_FAILED_TO_RESOLVE',
    ]);

    app(PlotDatasetRunService::class)->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);

    $issue->refresh();

    expect($issue->status)->toBe(DatasetIssue::STATUS_RESOLVED);
    expect(DatasetIssue::query()->active()->count())->toBe(0);
});
