<?php

namespace App\Http\Controllers;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const int RECENT_RUNS_LIMIT = 10;

    private const int RECENT_ISSUES_LIMIT = 10;

    public function index(): View
    {
        $since = Carbon::now()->subDays(7);

        $totalSources = MonitoredSource::query()->count();

        $sourcesWithAtLeastOneRun = MonitoredSource::query()
            ->whereHas('datasetComparisonRuns')
            ->count();

        $latestCompletedRunFinishedAt = DatasetComparisonRun::query()
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->max('finished_at');

        $failedRunsLast7Days = DatasetComparisonRun::query()
            ->where('status', 'failed')
            ->where(function ($q) use ($since) {
                $q->where('finished_at', '>=', $since)
                    ->orWhere(function ($q2) use ($since) {
                        $q2->whereNull('finished_at')
                            ->where('created_at', '>=', $since);
                    });
            })
            ->count();

        $issuesLast7Days = DatasetIssue::query()
            ->where('created_at', '>=', $since)
            ->count();

        $warningsLast7Days = DatasetIssue::query()
            ->where('created_at', '>=', $since)
            ->where('severity', 'warning')
            ->count();

        $errorsLast7Days = DatasetIssue::query()
            ->where('created_at', '>=', $since)
            ->where('severity', 'error')
            ->count();

        $changesLast7Days = ChangeLog::query()
            ->where('changed_at', '>=', $since)
            ->count();

        $recentRuns = DatasetComparisonRun::query()
            ->with('source')
            ->orderByDesc('id')
            ->limit(self::RECENT_RUNS_LIMIT)
            ->get();

        $recentIssues = DatasetIssue::query()
            ->with(['monitoredSource', 'datasetComparisonRun'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_ISSUES_LIMIT)
            ->get();

        return view('dashboard.index', [
            'summaryDateFrom' => $since->toDateString(),
            'totalSources' => $totalSources,
            'sourcesWithAtLeastOneRun' => $sourcesWithAtLeastOneRun,
            'latestCompletedRunFinishedAt' => $latestCompletedRunFinishedAt !== null
                ? Carbon::parse($latestCompletedRunFinishedAt)
                : null,
            'failedRunsLast7Days' => $failedRunsLast7Days,
            'issuesLast7Days' => $issuesLast7Days,
            'warningsLast7Days' => $warningsLast7Days,
            'errorsLast7Days' => $errorsLast7Days,
            'changesLast7Days' => $changesLast7Days,
            'recentRuns' => $recentRuns,
            'recentIssues' => $recentIssues,
        ]);
    }
}
