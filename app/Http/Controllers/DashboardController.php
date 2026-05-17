<?php

namespace App\Http\Controllers;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Support\PlotSnapshotDisplayLookup;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const int RECENT_RUNS_LIMIT = 10;

    private const int RECENT_ISSUES_LIMIT = 10;

    private const int RECENT_CHANGES_LIMIT = 10;

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

        $recentChanges = ChangeLog::query()
            ->with(['datasetComparisonRun.source'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_CHANGES_LIMIT)
            ->get();

        $plotDisplayLookupByRunId = $this->plotDisplayLookupsForRuns(
            $recentChanges->pluck('dataset_comparison_run_id')->unique()->filter()->values(),
        );

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
            'recentChanges' => $recentChanges,
            'plotDisplayLookupByRunId' => $plotDisplayLookupByRunId,
            'emptyPlotLookup' => PlotSnapshotDisplayLookup::empty(),
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $runIds
     * @return array<int, PlotSnapshotDisplayLookup>
     */
    private function plotDisplayLookupsForRuns(Collection $runIds): array
    {
        if ($runIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, DatasetComparisonRun> $runs */
        $runs = DatasetComparisonRun::query()
            ->whereIn('id', $runIds->all())
            ->get()
            ->keyBy('id');

        $snapshotIds = $runs
            ->flatMap(fn (DatasetComparisonRun $run) => array_filter([
                $run->current_snapshot_id,
                $run->previous_snapshot_id,
            ]))
            ->unique()
            ->values()
            ->all();

        /** @var array<int, DatasetSnapshot> $snapshotsById */
        $snapshotsById = $snapshotIds === []
            ? []
            : DatasetSnapshot::query()
                ->whereIn('id', $snapshotIds)
                ->get()
                ->keyBy('id')
                ->all();

        $lookups = [];
        foreach ($runIds as $runId) {
            $runId = (int) $runId;
            $run = $runs->get($runId);
            if ($run === null) {
                $lookups[$runId] = PlotSnapshotDisplayLookup::empty();

                continue;
            }

            $currentSnap = $run->current_snapshot_id !== null
                ? ($snapshotsById[$run->current_snapshot_id] ?? null)
                : null;
            $previousSnap = $run->previous_snapshot_id !== null
                ? ($snapshotsById[$run->previous_snapshot_id] ?? null)
                : null;

            $lookups[$runId] = PlotSnapshotDisplayLookup::fromPayloads(
                is_array($currentSnap?->payload) ? $currentSnap->payload : null,
                is_array($previousSnap?->payload) ? $previousSnap->payload : null,
            );
        }

        return $lookups;
    }
}
