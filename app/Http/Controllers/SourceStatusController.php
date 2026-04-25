<?php

namespace App\Http\Controllers;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\MonitoredSourceStatusService;
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

        return view('sources.show', [
            'source' => $source,
            'recentRuns' => $recentRuns,
            'latestRun' => $latestRun,
            'issueCountsByRunId' => $issueCountsByRunId,
            'severityCountsByRunId' => $severityCountsByRunId,
            'latestRunIssues' => $latestRunIssues,
        ]);
    }
}
