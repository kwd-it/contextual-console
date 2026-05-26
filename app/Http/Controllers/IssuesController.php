<?php

namespace App\Http\Controllers;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Support\PlotSnapshotDisplayLookup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IssuesController extends Controller
{
    private const int ISSUES_PER_PAGE = 100;

    public function show(DatasetIssue $issue): View
    {
        $issue->load(['monitoredSource', 'datasetComparisonRun', 'datasetSnapshot']);

        $plotLookup = $this->plotDisplayLookupForRunId($issue->dataset_comparison_run_id);
        $plotMeta = $plotLookup->forPlotEntity(
            $issue->entity_type !== null && $issue->entity_type !== ''
                ? (string) $issue->entity_type
                : null,
            $issue->entity_id,
        );

        return view('issues.show', [
            'issue' => $issue,
            'plotMeta' => $plotMeta,
            'issueStatusOptions' => DatasetIssue::STATUSES,
        ]);
    }

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

        $issues = $this->filteredIssuesQuery($filters)
            ->with(['monitoredSource', 'datasetComparisonRun'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::ISSUES_PER_PAGE)
            ->withQueryString();

        $plotDisplayLookupByRunId = $this->plotDisplayLookupsForRuns(
            $issues->getCollection()->pluck('dataset_comparison_run_id')->unique()->filter()->values(),
        );

        return view('issues.index', [
            'issues' => $issues,
            'plotDisplayLookupByRunId' => $plotDisplayLookupByRunId,
            'emptyPlotLookup' => PlotSnapshotDisplayLookup::empty(),
            'sources' => $sources,
            'severityOptions' => $severityOptions,
            'issueStatusFilterValues' => DatasetIssue::STATUS_FILTER_VALUES,
            'issueStatusOptions' => DatasetIssue::STATUSES,
            'filters' => $filters,
            'filtersActive' => $this->issueFiltersActive($filters),
        ]);
    }

    public function updateStatus(Request $request, DatasetIssue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(DatasetIssue::STATUSES)],
        ]);

        $issue->update(['status' => $validated['status']]);

        if ($request->input('return_to') === 'show') {
            return redirect()
                ->route('issues.show', $issue)
                ->with('status', 'Issue status updated.');
        }

        return redirect()
            ->route('issues.index', $this->issueIndexQueryFromRequest($request))
            ->with('status', 'Issue status updated.');
    }

    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(DatasetIssue::STATUSES)],
        ]);

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

        if (! $this->issueFiltersActive($filters)) {
            return redirect()
                ->route('issues.index')
                ->with('status', 'Apply at least one filter before updating issues in bulk.');
        }

        $updatedCount = $this->filteredIssuesQuery($filters)->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('issues.index', $this->issueIndexQueryFromFilters($filters))
            ->with('status', "Updated {$updatedCount} issues matching the current filters.");
    }

    /**
     * @param  array{source_id: int|null, issue_status: string|null, severity: string|null, issue_type: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}  $filters
     * @return Builder<DatasetIssue>
     */
    private function filteredIssuesQuery(array $filters): Builder
    {
        return DatasetIssue::query()
            ->when($filters['source_id'] !== null, fn ($q) => $q->where('monitored_source_id', $filters['source_id']))
            ->when(
                $filters['issue_status'] === DatasetIssue::FILTER_ACTIVE,
                fn ($q) => $q->active(),
            )
            ->when(
                $filters['issue_status'] !== null && $filters['issue_status'] !== DatasetIssue::FILTER_ACTIVE,
                fn ($q) => $q->where('status', $filters['issue_status']),
            )
            ->when($filters['severity'] !== null, fn ($q) => $q->where('severity', $filters['severity']))
            ->when($filters['issue_type'] !== null, fn ($q) => $q->where('issue_type', $filters['issue_type']))
            ->when($filters['date_from'] !== null, fn ($q) => $q->where('created_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== null, fn ($q) => $q->where('created_at', '<=', $filters['date_to']));
    }

    /**
     * @return array<string, int|string>
     */
    private function issueIndexQueryFromRequest(Request $request): array
    {
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

        return $this->issueIndexQueryFromFilters(
            $this->resolvedIssueFilters($request, $severityOptions, $issueTypeOptions),
        );
    }

    /**
     * @param  array{source_id: int|null, issue_status: string|null, severity: string|null, issue_type: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}  $filters
     * @return array<string, int|string>
     */
    private function issueIndexQueryFromFilters(array $filters): array
    {
        $query = [];

        if ($filters['source_id'] !== null) {
            $query['source'] = $filters['source_id'];
        }

        if ($filters['issue_status'] !== null) {
            $query['issue_status'] = $filters['issue_status'];
        }

        if ($filters['severity'] !== null) {
            $query['severity'] = $filters['severity'];
        }

        if ($filters['issue_type'] !== null) {
            $query['issue_type'] = $filters['issue_type'];
        }

        if ($filters['date_from_input'] !== null) {
            $query['date_from'] = $filters['date_from_input'];
        }

        if ($filters['date_to_input'] !== null) {
            $query['date_to'] = $filters['date_to_input'];
        }

        return $query;
    }

    /**
     * @param  list<string>  $severityOptions
     * @param  list<string>  $issueTypeOptions
     * @return array{source_id: int|null, issue_status: string|null, severity: string|null, issue_type: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}
     */
    private function resolvedIssueFilters(Request $request, array $severityOptions, array $issueTypeOptions): array
    {
        $sourceId = null;
        $rawSource = $request->input('source');
        if (is_numeric($rawSource)) {
            $id = (int) $rawSource;
            if ($id > 0 && MonitoredSource::query()->whereKey($id)->exists()) {
                $sourceId = $id;
            }
        }

        $issueStatus = null;
        $rawIssueStatus = $request->input('issue_status');
        if (is_string($rawIssueStatus) && in_array($rawIssueStatus, DatasetIssue::STATUS_FILTER_VALUES, true)) {
            $issueStatus = $rawIssueStatus;
        }

        $severity = null;
        $rawSeverity = $request->input('severity');
        if (is_string($rawSeverity) && $rawSeverity !== '' && in_array($rawSeverity, $severityOptions, true)) {
            $severity = $rawSeverity;
        }

        $issueType = null;
        $rawIssueType = $request->input('issue_type');
        if (is_string($rawIssueType) && $rawIssueType !== '' && in_array($rawIssueType, $issueTypeOptions, true)) {
            $issueType = $rawIssueType;
        }

        $dateFrom = $this->parseDateBoundary($request->input('date_from'), true);
        $dateTo = $this->parseDateBoundary($request->input('date_to'), false);

        return [
            'source_id' => $sourceId,
            'issue_status' => $issueStatus,
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
     * @param  array{source_id: int|null, issue_status: string|null, severity: string|null, issue_type: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}  $filters
     */
    private function issueFiltersActive(array $filters): bool
    {
        return $filters['source_id'] !== null
            || $filters['issue_status'] !== null
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

    private function plotDisplayLookupForRunId(?int $runId): PlotSnapshotDisplayLookup
    {
        if ($runId === null) {
            return PlotSnapshotDisplayLookup::empty();
        }

        $lookups = $this->plotDisplayLookupsForRuns(collect([$runId]));

        return $lookups[$runId] ?? PlotSnapshotDisplayLookup::empty();
    }
}
