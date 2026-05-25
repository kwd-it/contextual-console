<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Support\DashboardViewData;
use App\Support\PlotSnapshotDisplayLookup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the dashboard view variable contract', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $data = app(DashboardViewData::class)->forIndex();

        expect(array_keys($data))->toBe([
            'summaryDateFrom',
            'totalSources',
            'latestCompletedRunFinishedAt',
            'currentFailedRuns',
            'recoveredFailedRuns7d',
            'activeIssuesCount',
            'activeInfosCount',
            'activeWarningsCount',
            'activeErrorsCount',
            'changesLast7Days',
            'recentRuns',
            'recentIssues',
            'recentChanges',
            'plotDisplayLookupByRunId',
            'emptyPlotLookup',
            'hasInactiveIssues',
            'developmentOverviewGroups',
        ]);
        expect($data['summaryDateFrom'])->toBe('2026-05-07');
        expect($data['emptyPlotLookup'])->toBeInstanceOf(PlotSnapshotDisplayLookup::class);
        expect($data['developmentOverviewGroups'])->toBe([]);
    } finally {
        Carbon::setTestNow();
    }
});

it('limits dashboard recent runs, changes, and issues to five items each', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:dash-viewdata-limits',
        'name' => 'Dashboard ViewData Limits Source',
    ]);

    $runs = [];
    for ($i = 0; $i < 6; $i++) {
        $runs[] = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subMinutes(10 - $i),
            'finished_at' => now()->subMinutes(9 - $i),
        ]);
    }

    for ($i = 0; $i < 6; $i++) {
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $runs[$i]->id,
            'issue_type' => 'limit',
            'severity' => 'info',
            'message' => 'limit-issue-'.$i,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    for ($i = 0; $i < 6; $i++) {
        ChangeLog::create([
            'dataset_comparison_run_id' => $runs[$i]->id,
            'entity_type' => 'plot',
            'entity_id' => (string) (200 + $i),
            'field' => 'status',
            'old_value' => 'a',
            'new_value' => 'b',
            'changed_at' => now()->subMinutes($i),
        ]);
    }

    $data = app(DashboardViewData::class)->forIndex();

    expect($data['recentRuns'])->toHaveCount(5);
    expect($data['recentIssues'])->toHaveCount(5);
    expect($data['recentChanges'])->toHaveCount(5);
});

it('excludes ignored and resolved issues from dashboard recent active issues', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:dash-viewdata-active',
        'name' => 'Dashboard ViewData Active Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'inactive',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_IGNORED,
        'message' => 'inactive-issue',
        'created_at' => now(),
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'active-issue',
        'created_at' => now()->subMinute(),
    ]);

    $data = app(DashboardViewData::class)->forIndex();

    expect($data['recentIssues'])->toHaveCount(1);
    expect($data['recentIssues']->first()?->message)->toBe('active-issue');
    expect($data['hasInactiveIssues'])->toBeTrue();
});

it('counts current and recovered failed runs separately for the last seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $recoveredSource = MonitoredSource::create([
            'key' => 'hb:dash-failed-recovered',
            'name' => 'Recovered Failure Source',
        ]);
        $activeSource = MonitoredSource::create([
            'key' => 'hb:dash-failed-active',
            'name' => 'Active Failure Source',
        ]);

        $recoveredFailure = DatasetComparisonRun::create([
            'source_id' => $recoveredSource->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDays(2),
            'finished_at' => now()->subDays(2),
        ]);
        DatasetComparisonRun::create([
            'source_id' => $recoveredSource->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);

        DatasetComparisonRun::create([
            'source_id' => $activeSource->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour(),
        ]);

        $data = app(DashboardViewData::class)->forIndex();

        expect($data['currentFailedRuns'])->toBe(1)
            ->and($data['recoveredFailedRuns7d'])->toBe(1)
            ->and($recoveredFailure->id)->toBeLessThan(
                DatasetComparisonRun::query()->where('source_id', $recoveredSource->id)->max('id'),
            );
    } finally {
        Carbon::setTestNow();
    }
});

it('counts a latest failed run as current even when it is older than seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $source = MonitoredSource::create([
            'key' => 'hb:dash-failed-stale-latest',
            'name' => 'Stale Latest Failure Source',
        ]);

        DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDays(30),
            'finished_at' => now()->subDays(30),
        ]);

        $data = app(DashboardViewData::class)->forIndex();

        expect($data['currentFailedRuns'])->toBe(1)
            ->and($data['recoveredFailedRuns7d'])->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});

it('does not count recovered failed runs older than seven days', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $source = MonitoredSource::create([
            'key' => 'hb:dash-failed-stale-recovered',
            'name' => 'Stale Recovered Failure Source',
        ]);

        DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDays(30),
            'finished_at' => now()->subDays(30),
        ]);
        DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDays(29),
            'finished_at' => now()->subDays(29),
        ]);

        $data = app(DashboardViewData::class)->forIndex();

        expect($data['currentFailedRuns'])->toBe(0)
            ->and($data['recoveredFailedRuns7d'])->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});
