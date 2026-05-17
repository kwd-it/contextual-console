<?php

namespace App\Http\Controllers;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Core\Services\MonitoredSourceStatusService;
use App\Support\DevelopmentDetailViewData;
use App\Support\PlotSnapshotDisplayLookup;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SourceStatusController extends Controller
{
    public function index(MonitoredSourceStatusService $status): View
    {
        return view('sources.index', [
            'summaries' => $status->summaries(),
        ]);
    }

    public function show(MonitoredSource $source): View
    {
        $recentRuns = DatasetComparisonRun::query()
            ->where('source_id', $source->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $latestRun = $recentRuns->first();

        $runIds = $recentRuns->pluck('id')->map(fn ($id) => (int) $id)->all();

        /** @var array<int, int> $issueCountsByRunId */
        $issueCountsByRunId = [];

        /** @var array<int, array<string, int>> $severityCountsByRunId */
        $severityCountsByRunId = [];

        if ($runIds !== []) {
            $issueCountsByRunId = DatasetIssue::query()
                ->select('dataset_comparison_run_id', DB::raw('count(*) as total'))
                ->whereIn('dataset_comparison_run_id', $runIds)
                ->groupBy('dataset_comparison_run_id')
                ->pluck('total', 'dataset_comparison_run_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $severityRows = DatasetIssue::query()
                ->select('dataset_comparison_run_id', 'severity', DB::raw('count(*) as total'))
                ->whereIn('dataset_comparison_run_id', $runIds)
                ->groupBy('dataset_comparison_run_id', 'severity')
                ->get();

            foreach ($severityRows as $row) {
                $runId = (int) $row->dataset_comparison_run_id;
                $severity = (string) $row->severity;
                $total = (int) $row->total;

                $severityCountsByRunId[$runId] ??= [];
                $severityCountsByRunId[$runId][$severity] = $total;
            }
        }

        $latestRunIssues = collect();
        if ($latestRun !== null) {
            $latestRunIssues = DatasetIssue::query()
                ->where('dataset_comparison_run_id', $latestRun->id)
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $latestRunChanges = collect();
        if ($latestRun !== null) {
            $latestRunChanges = ChangeLog::query()
                ->where('dataset_comparison_run_id', $latestRun->id)
                ->orderBy('entity_id')
                ->orderBy('field')
                ->orderBy('id')
                ->limit(250)
                ->get();
        }

        $plotDisplayLookup = PlotSnapshotDisplayLookup::empty();
        if ($latestRun !== null) {
            $snapshotIds = array_values(array_unique(array_filter([
                $latestRun->current_snapshot_id,
                $latestRun->previous_snapshot_id,
            ])));

            if ($snapshotIds !== []) {
                /** @var array<int, DatasetSnapshot> $snapshotsById */
                $snapshotsById = DatasetSnapshot::query()
                    ->whereIn('id', $snapshotIds)
                    ->get()
                    ->keyBy('id')
                    ->all();

                $currentSnap = $latestRun->current_snapshot_id !== null
                    ? ($snapshotsById[$latestRun->current_snapshot_id] ?? null)
                    : null;
                $previousSnap = $latestRun->previous_snapshot_id !== null
                    ? ($snapshotsById[$latestRun->previous_snapshot_id] ?? null)
                    : null;

                $plotDisplayLookup = PlotSnapshotDisplayLookup::fromPayloads(
                    is_array($currentSnap?->payload) ? $currentSnap->payload : null,
                    is_array($previousSnap?->payload) ? $previousSnap->payload : null,
                );
            }
        }

        return view('sources.show', [
            'source' => $source,
            'recentRuns' => $recentRuns,
            'latestRun' => $latestRun,
            'issueCountsByRunId' => $issueCountsByRunId,
            'severityCountsByRunId' => $severityCountsByRunId,
            'latestRunIssues' => $latestRunIssues,
            'latestRunChanges' => $latestRunChanges,
            'plotDisplayLookup' => $plotDisplayLookup,
        ]);
    }

    public function showDevelopment(
        MonitoredSource $source,
        string $development,
        DevelopmentDetailViewData $developmentDetailViewData,
    ): View {
        return view('sources.development-show', $developmentDetailViewData->forShow($source, $development));
    }

    public function showRun(MonitoredSource $source, DatasetComparisonRun $run): View
    {
        abort_unless($run->source_id === $source->id, 404);

        $runIssues = DatasetIssue::query()
            ->where('dataset_comparison_run_id', $run->id)
            ->orderByDesc('id')
            ->get();

        $runChanges = ChangeLog::query()
            ->where('dataset_comparison_run_id', $run->id)
            ->orderBy('entity_id')
            ->orderBy('field')
            ->orderBy('id')
            ->get();

        $severityCounts = [];
        foreach ($runIssues as $issue) {
            $sev = strtolower((string) $issue->severity);
            $severityCounts[$sev] = ($severityCounts[$sev] ?? 0) + 1;
        }

        $plotDisplayLookup = PlotSnapshotDisplayLookup::empty();
        $snapshotIds = array_values(array_unique(array_filter([
            $run->current_snapshot_id,
            $run->previous_snapshot_id,
        ])));

        if ($snapshotIds !== []) {
            /** @var array<int, DatasetSnapshot> $snapshotsById */
            $snapshotsById = DatasetSnapshot::query()
                ->whereIn('id', $snapshotIds)
                ->get()
                ->keyBy('id')
                ->all();

            $currentSnap = $run->current_snapshot_id !== null
                ? ($snapshotsById[$run->current_snapshot_id] ?? null)
                : null;
            $previousSnap = $run->previous_snapshot_id !== null
                ? ($snapshotsById[$run->previous_snapshot_id] ?? null)
                : null;

            $plotDisplayLookup = PlotSnapshotDisplayLookup::fromPayloads(
                is_array($currentSnap?->payload) ? $currentSnap->payload : null,
                is_array($previousSnap?->payload) ? $previousSnap->payload : null,
            );
        }

        return view('sources.run-show', [
            'source' => $source,
            'run' => $run,
            'runIssues' => $runIssues,
            'runChanges' => $runChanges,
            'severityCounts' => $severityCounts,
            'plotDisplayLookup' => $plotDisplayLookup,
        ]);
    }
}
