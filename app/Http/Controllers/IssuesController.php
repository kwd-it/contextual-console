<?php

namespace App\Http\Controllers;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Support\PlotSnapshotDisplayLookup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class IssuesController extends Controller
{
    private const int ISSUE_LIMIT = 100;

    public function index(Request $request): View
    {
        $sources = MonitoredSource::query()
            ->orderBy('name')
            ->get();

        $severityOptions = DatasetIssue::query()
            ->whereNotNull('severity')
            ->where('severity', '!=', '')
            ->distinct()
            ->orderBy('severity')
            ->pluck('severity')
            ->all();

        $issueTypeOptions = DatasetIssue::query()
            ->whereNotNull('issue_type')
            ->where('issue_type', '!=', '')
            ->distinct()
            ->orderBy('issue_type')
            ->pluck('issue_type')
            ->all();

        $filters = $this->resolvedIssueFilters($request, $severityOptions, $issueTypeOptions);

        $issues = DatasetIssue::query()
            ->with(['monitoredSource', 'datasetComparisonRun'])
            ->when($filters['source_id'] !== null, fn ($q) => $q->where('monitored_source_id', $filters['source_id']))
            ->when($filters['severity'] !== null, fn ($q) => $q->where('severity', $filters['severity']))
            ->when($filters['issue_type'] !== null, fn ($q) => $q->where('issue_type', $filters['issue_type']))
            ->when($filters['date_from'] !== null, fn ($q) => $q->where('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== null, fn ($q) => $q->where('created_at', '<=', $filters['date_to']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ISSUE_LIMIT)
            ->get();

        $plotDisplayLookupByRunId = $this->plotDisplayLookupsForRuns(
            $issues->pluck('dataset_comparison_run_id')->unique()->filter()->values(),
        );

        return view('issues.index', [
            'issues' => $issues,
            'plotDisplayLookupByRunId' => $plotDisplayLookupByRunId,
            'emptyPlotLookup' => PlotSnapshotDisplayLookup::empty(),
            'issueLimit' => self::ISSUE_LIMIT,
            'sources' => $sources,
            'severityOptions' => $severityOptions,
            'issueTypeOptions' => $issueTypeOptions,
            'filters' => $filters,
            'filtersActive' => $this->issueFiltersActive($filters),
        ]);
    }

    /**
     * @param  list<string>  $severityOptions
     * @param  list<string>  $issueTypeOptions
     * @return array{source_id: int|null, severity: string|null, issue_type: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}
     */
    private function resolvedIssueFilters(Request $request, array $severityOptions, array $issueTypeOptions): array
    {
        $sourceId = null;
        $rawSource = $request->query('source');
        if (is_numeric($rawSource)) {
            $id = (int) $rawSource;
            if ($id > 0 && MonitoredSource::query()->whereKey($id)->exists()) {
                $sourceId = $id;
            }
        }

        $severity = null;
        $rawSeverity = $request->query('severity');
        if (is_string($rawSeverity) && $rawSeverity !== '' && in_array($rawSeverity, $severityOptions, true)) {
            $severity = $rawSeverity;
        }

        $issueType = null;
        $rawIssueType = $request->query('issue_type');
        if (is_string($rawIssueType) && $rawIssueType !== '' && in_array($rawIssueType, $issueTypeOptions, true)) {
            $issueType = $rawIssueType;
        }

        $dateFrom = $this->parseDateBoundary($request->query('date_from'), true);
        $dateTo = $this->parseDateBoundary($request->query('date_to'), false);

        return [
            'source_id' => $sourceId,
            'severity' => $severity,
            'issue_type' => $issueType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_input' => $dateFrom?->toDateString(),
            'date_to_input' => $dateTo?->toDateString(),
        ];
    }

    /**
     * @param  mixed  $value
     */
    private function parseDateBoundary($value, bool $startOfDay): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $c = Carbon::createFromFormat('Y-m-d', $value);

            return $startOfDay ? $c->copy()->startOfDay() : $c->copy()->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{source_id: int|null, severity: string|null, issue_type: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}  $filters
     */
    private function issueFiltersActive(array $filters): bool
    {
        return $filters['source_id'] !== null
            || $filters['severity'] !== null
            || $filters['issue_type'] !== null
            || $filters['date_from'] !== null
            || $filters['date_to'] !== null;
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
