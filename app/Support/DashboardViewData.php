<?php

namespace App\Support;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DashboardViewData
{
    private const int DASHBOARD_RECENT_RUNS_LIMIT = 5;

    private const int DASHBOARD_RECENT_CHANGES_LIMIT = 5;

    private const int DASHBOARD_RECENT_ISSUES_LIMIT = 5;

    private const int DEVELOPMENT_OVERVIEW_LIMIT = 10;

    /** @var array<int, string> */
    private const KNOWN_PLOT_STATUSES = ['available', 'reserved', 'sold', 'coming_soon'];

    /**
     * @return array{
     *   summaryDateFrom: string,
     *   totalSources: int,
     *   latestCompletedRunFinishedAt: ?Carbon,
     *   failedRunsLast7Days: int,
     *   activeIssuesCount: int,
     *   activeInfosCount: int,
     *   activeWarningsCount: int,
     *   activeErrorsCount: int,
     *   changesLast7Days: int,
     *   recentRuns: \Illuminate\Database\Eloquent\Collection<int, DatasetComparisonRun>,
     *   recentIssues: \Illuminate\Database\Eloquent\Collection<int, DatasetIssue>,
     *   recentChanges: \Illuminate\Database\Eloquent\Collection<int, ChangeLog>,
     *   plotDisplayLookupByRunId: array<int, PlotSnapshotDisplayLookup>,
     *   emptyPlotLookup: PlotSnapshotDisplayLookup,
     *   hasInactiveIssues: bool,
     *   developmentOverviewGroups: list<array{
     *     source: MonitoredSource,
     *     development: string,
     *     total: int,
     *     available: int,
     *     reserved: int,
     *     sold: int,
     *     coming_soon: int,
     *     unknown: int,
     *   }>,
     * }
     */
    public function forIndex(): array
    {
        $since = Carbon::now()->subDays(7);

        $totalSources = MonitoredSource::query()->count();

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

        $activeIssuesQuery = fn () => DatasetIssue::query()->active();

        $activeIssuesCount = $activeIssuesQuery()->count();

        $activeInfosCount = $activeIssuesQuery()
            ->where('severity', 'info')
            ->count();

        $activeWarningsCount = $activeIssuesQuery()
            ->where('severity', 'warning')
            ->count();

        $activeErrorsCount = $activeIssuesQuery()
            ->where('severity', 'error')
            ->count();

        $hasInactiveIssues = DatasetIssue::query()
            ->whereIn('status', [DatasetIssue::STATUS_IGNORED, DatasetIssue::STATUS_RESOLVED])
            ->exists();

        $changesLast7Days = ChangeLog::query()
            ->where('changed_at', '>=', $since)
            ->count();

        $recentRuns = DatasetComparisonRun::query()
            ->with('source')
            ->orderByDesc('id')
            ->limit(self::DASHBOARD_RECENT_RUNS_LIMIT)
            ->get();

        $recentIssues = DatasetIssue::query()
            ->active()
            ->with(['monitoredSource', 'datasetComparisonRun'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::DASHBOARD_RECENT_ISSUES_LIMIT)
            ->get();

        $recentChanges = ChangeLog::query()
            ->with(['datasetComparisonRun.source'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(self::DASHBOARD_RECENT_CHANGES_LIMIT)
            ->get();

        $plotDisplayLookupByRunId = $this->plotDisplayLookupsForRuns(
            $recentChanges->pluck('dataset_comparison_run_id')->unique()->filter()->values(),
        );

        $developmentOverviewGroups = $this->developmentOverviewGroups();

        return [
            'summaryDateFrom' => $since->toDateString(),
            'totalSources' => $totalSources,
            'latestCompletedRunFinishedAt' => $latestCompletedRunFinishedAt !== null
                ? Carbon::parse($latestCompletedRunFinishedAt)
                : null,
            'failedRunsLast7Days' => $failedRunsLast7Days,
            'activeIssuesCount' => $activeIssuesCount,
            'activeInfosCount' => $activeInfosCount,
            'activeWarningsCount' => $activeWarningsCount,
            'activeErrorsCount' => $activeErrorsCount,
            'changesLast7Days' => $changesLast7Days,
            'recentRuns' => $recentRuns,
            'recentIssues' => $recentIssues,
            'recentChanges' => $recentChanges,
            'plotDisplayLookupByRunId' => $plotDisplayLookupByRunId,
            'emptyPlotLookup' => PlotSnapshotDisplayLookup::empty(),
            'hasInactiveIssues' => $hasInactiveIssues,
            'developmentOverviewGroups' => $developmentOverviewGroups,
        ];
    }

    /**
     * @return list<array{
     *   source: MonitoredSource,
     *   development: string,
     *   total: int,
     *   available: int,
     *   reserved: int,
     *   sold: int,
     *   coming_soon: int,
     *   unknown: int,
     * }>
     */
    private function developmentOverviewGroups(): array
    {
        $latestRunIdsBySourceId = DatasetComparisonRun::query()
            ->select('source_id', DB::raw('max(id) as latest_run_id'))
            ->whereIn('status', ['completed', 'baseline'])
            ->whereNotNull('current_snapshot_id')
            ->groupBy('source_id')
            ->pluck('latest_run_id', 'source_id');

        if ($latestRunIdsBySourceId->isEmpty()) {
            return [];
        }

        /** @var Collection<int, DatasetComparisonRun> $runsBySourceId */
        $runsBySourceId = DatasetComparisonRun::query()
            ->with('source')
            ->whereIn('id', $latestRunIdsBySourceId->values())
            ->get()
            ->keyBy('source_id');

        $snapshotIds = $runsBySourceId
            ->pluck('current_snapshot_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($snapshotIds === []) {
            return [];
        }

        /** @var array<int, DatasetSnapshot> $snapshotsById */
        $snapshotsById = DatasetSnapshot::query()
            ->whereIn('id', $snapshotIds)
            ->get()
            ->keyBy('id')
            ->all();

        $groups = [];

        foreach ($runsBySourceId as $sourceId => $run) {
            $source = $run->source;
            if ($source === null) {
                continue;
            }

            $snapshot = $run->current_snapshot_id !== null
                ? ($snapshotsById[$run->current_snapshot_id] ?? null)
                : null;
            $payload = is_array($snapshot?->payload) ? $snapshot->payload : null;

            if ($payload === null || $payload === []) {
                continue;
            }

            $dataUpdatedAt = $run->finished_at ?? $run->started_at;

            foreach ($this->developmentGroupsFromPayload($payload) as $development => $counts) {
                $groups[] = [
                    'source' => $source,
                    'development' => $development,
                    'total' => $counts['total'],
                    'available' => $counts['available'],
                    'reserved' => $counts['reserved'],
                    'sold' => $counts['sold'],
                    'coming_soon' => $counts['coming_soon'],
                    'unknown' => $counts['unknown'],
                    '_sort_total' => $counts['total'],
                    '_sort_updated_at' => $dataUpdatedAt?->getTimestamp() ?? 0,
                ];
            }
        }

        usort($groups, static function (array $a, array $b): int {
            $byTotal = $b['_sort_total'] <=> $a['_sort_total'];
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return $b['_sort_updated_at'] <=> $a['_sort_updated_at'];
        });

        $trimmed = array_slice($groups, 0, self::DEVELOPMENT_OVERVIEW_LIMIT);

        return array_map(static function (array $row): array {
            unset($row['_sort_total'], $row['_sort_updated_at']);

            return $row;
        }, $trimmed);
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return array<string, array{total: int, available: int, reserved: int, sold: int, coming_soon: int, unknown: int}>
     */
    private function developmentGroupsFromPayload(array $payload): array
    {
        $groups = [];

        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }

            $development = PlotDevelopmentLabel::fromPlot($item);
            $statusBucket = $this->plotStatusBucket($item['status'] ?? null);

            $groups[$development] ??= [
                'total' => 0,
                'available' => 0,
                'reserved' => 0,
                'sold' => 0,
                'coming_soon' => 0,
                'unknown' => 0,
            ];

            $groups[$development]['total']++;
            $groups[$development][$statusBucket]++;
        }

        return $groups;
    }

    private function plotStatusBucket(mixed $status): string
    {
        if (! is_string($status)) {
            return 'unknown';
        }

        $normalized = strtolower(trim($status));

        if ($normalized === '' || ! in_array($normalized, self::KNOWN_PLOT_STATUSES, true)) {
            return 'unknown';
        }

        return $normalized;
    }

    private function nonEmptyPlotString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return html_entity_decode($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
