<?php

namespace App\Http\Controllers;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Support\PlotSnapshotDisplayLookup;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ChangesController extends Controller
{
    private const int CHANGE_LIMIT = 100;

    public function index(): View
    {
        $changes = ChangeLog::query()
            ->with(['datasetComparisonRun.source'])
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(self::CHANGE_LIMIT)
            ->get();

        $plotDisplayLookupByRunId = $this->plotDisplayLookupsForRuns(
            $changes->pluck('dataset_comparison_run_id')->unique()->filter()->values(),
        );

        return view('changes.index', [
            'changes' => $changes,
            'plotDisplayLookupByRunId' => $plotDisplayLookupByRunId,
            'emptyPlotLookup' => PlotSnapshotDisplayLookup::empty(),
            'changeLimit' => self::CHANGE_LIMIT,
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
