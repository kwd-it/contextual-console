<?php

namespace App\Core\Services;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use Illuminate\Support\Facades\DB;

class MonitoredSourceStatusService
{
    /**
     * @return array<int, array{
     *   source_id: int,
     *   source_key: string,
     *   source_name: string,
     *   latest_run_id: int|null,
     *   latest_run_status: string|null,
     *   latest_run_started_at: \Illuminate\Support\Carbon|null,
     *   latest_run_finished_at: \Illuminate\Support\Carbon|null,
     *   current_snapshot_id: int|null,
     *   previous_snapshot_id: int|null,
     *   added: int,
     *   removed: int,
     *   changed: int,
     *   unchanged: int,
     *   issue_count: int,
     *   error_count: int,
     *   warning_count: int,
     *   info_count: int
     * }>
     */
    public function summaries(): array
    {
        $sources = MonitoredSource::query()
            ->orderBy('id')
            ->get();

        $latestRunIdsBySourceId = DatasetComparisonRun::query()
            ->select('source_id', DB::raw('max(id) as latest_run_id'))
            ->groupBy('source_id')
            ->pluck('latest_run_id', 'source_id');

        /** @var array<int, DatasetComparisonRun> $latestRunsBySourceId */
        $latestRunsBySourceId = DatasetComparisonRun::query()
            ->whereIn('id', $latestRunIdsBySourceId->values())
            ->get()
            ->keyBy('source_id')
            ->all();

        $latestRunIds = array_values(array_unique(array_filter(
            $latestRunIdsBySourceId->values()->all(),
            fn ($id) => $id !== null
        )));

        /** @var array<int, int> $issueCountsByRunId */
        $issueCountsByRunId = [];

        /** @var array<int, array<string, int>> $severityCountsByRunId */
        $severityCountsByRunId = [];

        if ($latestRunIds !== []) {
            $issueCountsByRunId = DatasetIssue::query()
                ->select('dataset_comparison_run_id', DB::raw('count(*) as total'))
                ->whereIn('dataset_comparison_run_id', $latestRunIds)
                ->groupBy('dataset_comparison_run_id')
                ->pluck('total', 'dataset_comparison_run_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $severityRows = DatasetIssue::query()
                ->select('dataset_comparison_run_id', 'severity', DB::raw('count(*) as total'))
                ->whereIn('dataset_comparison_run_id', $latestRunIds)
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

        $summaries = [];

        foreach ($sources as $source) {
            $run = $latestRunsBySourceId[$source->id] ?? null;

            $added = 0;
            $removed = 0;
            $changed = 0;
            $unchanged = 0;

            if ($run?->status === 'completed' && is_array($run->summary)) {
                $added = (int) ($run->summary['added'] ?? 0);
                $removed = (int) ($run->summary['removed'] ?? 0);
                $changed = (int) ($run->summary['changed'] ?? 0);
                $unchanged = (int) ($run->summary['unchanged'] ?? 0);
            }

            $runId = $run?->id;
            $issueCount = (int) ($runId === null ? 0 : ($issueCountsByRunId[$runId] ?? 0));
            $severityCounts = $runId === null ? [] : ($severityCountsByRunId[$runId] ?? []);

            $summaries[] = [
                'source_id' => (int) $source->id,
                'source_key' => (string) $source->key,
                'source_name' => (string) $source->name,
                'latest_run_id' => $runId === null ? null : (int) $runId,
                'latest_run_status' => $run?->status,
                'latest_run_started_at' => $run?->started_at,
                'latest_run_finished_at' => $run?->finished_at,
                'current_snapshot_id' => $run?->current_snapshot_id,
                'previous_snapshot_id' => $run?->previous_snapshot_id,
                'added' => $added,
                'removed' => $removed,
                'changed' => $changed,
                'unchanged' => $unchanged,
                'issue_count' => $issueCount,
                'error_count' => (int) ($severityCounts['error'] ?? 0),
                'warning_count' => (int) ($severityCounts['warning'] ?? 0),
                'info_count' => (int) ($severityCounts['info'] ?? 0),
            ];
        }

        return $summaries;
    }
}
