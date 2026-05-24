<?php

namespace App\Http\Controllers;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Support\PlotSnapshotDisplayLookup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ChangesController extends Controller
{
    private const int CHANGES_PER_PAGE = 100;

    public function index(Request $request): View
    {
        $sources = MonitoredSource::query()
            ->orderBy('name')
            ->get();

        $fieldOptions = ChangeLog::query()
            ->whereNotNull('field')
            ->where('field', '!=', '')
            ->distinct()
            ->orderBy('field')
            ->pluck('field')
            ->all();

        $filters = $this->resolvedChangeFilters($request, $fieldOptions);

        $changes = $this->filteredChangesQuery($filters)
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->paginate(self::CHANGES_PER_PAGE)
            ->withQueryString();

        $plotDisplayLookupByRunId = $this->plotDisplayLookupsForRuns(
            $changes->getCollection()->pluck('dataset_comparison_run_id')->unique()->filter()->values(),
        );

        return view('changes.index', [
            'changes' => $changes,
            'plotDisplayLookupByRunId' => $plotDisplayLookupByRunId,
            'emptyPlotLookup' => PlotSnapshotDisplayLookup::empty(),
            'sources' => $sources,
            'fieldOptions' => $fieldOptions,
            'filters' => $filters,
            'filtersActive' => $this->changeFiltersActive($filters),
        ]);
    }

    /**
     * @param  array{source_id: int|null, field: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}  $filters
     */
    private function filteredChangesQuery(array $filters): Builder
    {
        return ChangeLog::query()
            ->with(['datasetComparisonRun.source'])
            ->when($filters['source_id'] !== null, function ($q) use ($filters): void {
                $q->whereHas('datasetComparisonRun', function ($q2) use ($filters): void {
                    $q2->where('source_id', $filters['source_id']);
                });
            })
            ->when($filters['field'] !== null, fn ($q) => $q->where('field', $filters['field']))
            ->when($filters['date_from'] !== null, fn ($q) => $q->where('changed_at', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== null, fn ($q) => $q->where('changed_at', '<=', $filters['date_to']));
    }

    /**
     * @param  list<string>  $fieldOptions
     * @return array{source_id: int|null, field: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}
     */
    private function resolvedChangeFilters(Request $request, array $fieldOptions): array
    {
        $sourceId = null;
        $rawSource = $request->query('source');
        if (is_numeric($rawSource)) {
            $id = (int) $rawSource;
            if ($id > 0 && MonitoredSource::query()->whereKey($id)->exists()) {
                $sourceId = $id;
            }
        }

        $field = null;
        $rawField = $request->query('field');
        if (is_string($rawField) && $rawField !== '' && in_array($rawField, $fieldOptions, true)) {
            $field = $rawField;
        }

        $dateFrom = $this->parseDateBoundary($request->query('date_from'), true);
        $dateTo = $this->parseDateBoundary($request->query('date_to'), false);

        $dateFromInput = $dateFrom?->toDateString();
        $dateToInput = $dateTo?->toDateString();

        return [
            'source_id' => $sourceId,
            'field' => $field,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'date_from_input' => $dateFromInput,
            'date_to_input' => $dateToInput,
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
     * @param  array{source_id: int|null, field: string|null, date_from: Carbon|null, date_to: Carbon|null, date_from_input: string|null, date_to_input: string|null}  $filters
     */
    private function changeFiltersActive(array $filters): bool
    {
        return $filters['source_id'] !== null
            || $filters['field'] !== null
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
