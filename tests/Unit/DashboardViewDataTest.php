<?php

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
            'sourcesWithAtLeastOneRun',
            'latestCompletedRunFinishedAt',
            'failedRunsLast7Days',
            'issuesLast7Days',
            'warningsLast7Days',
            'errorsLast7Days',
            'changesLast7Days',
            'recentRuns',
            'recentIssues',
            'recentChanges',
            'plotDisplayLookupByRunId',
            'emptyPlotLookup',
            'developmentOverviewGroups',
        ]);
        expect($data['summaryDateFrom'])->toBe('2026-05-07');
        expect($data['emptyPlotLookup'])->toBeInstanceOf(PlotSnapshotDisplayLookup::class);
        expect($data['developmentOverviewGroups'])->toBe([]);
    } finally {
        Carbon::setTestNow();
    }
});
