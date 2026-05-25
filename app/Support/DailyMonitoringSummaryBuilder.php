<?php

namespace App\Support;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Services\SourceRunFailedIssueService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DailyMonitoringSummaryBuilder
{
    public function build(int $hours): string
    {
        return $this->buildReport($hours)->toPlainText();
    }

    public function buildReport(int $hours): DailyMonitoringSummaryReport
    {
        if ($hours <= 0) {
            throw new \InvalidArgumentException('--hours must be a positive integer.');
        }

        $cutoff = now()->subHours($hours);

        $periodLatestRunIdsBySourceId = $this->periodLatestRunIdsBySourceId($cutoff);

        $periodLatestRunIds = array_values(array_unique(array_filter(
            $periodLatestRunIdsBySourceId->values()->all(),
            fn ($id) => $id !== null
        )));

        if ($periodLatestRunIds === []) {
            return new DailyMonitoringSummaryReport(
                emptyMessage: sprintf(
                    'No monitoring runs found in the last %d hour(s) (since %s).',
                    $hours,
                    DisplayTimestamp::format($cutoff),
                ),
            );
        }

        $sourceIds = $periodLatestRunIdsBySourceId->keys()->all();

        $overallLatestRunIdsBySourceId = DatasetComparisonRun::query()
            ->whereIn('source_id', $sourceIds)
            ->select('source_id', DB::raw('max(id) as latest_run_id'))
            ->groupBy('source_id')
            ->pluck('latest_run_id', 'source_id');

        $runs = DatasetComparisonRun::query()
            ->with('source')
            ->whereIn('id', $periodLatestRunIds)
            ->orderBy('source_id')
            ->get();

        $overallLatestRuns = DatasetComparisonRun::query()
            ->whereIn('id', array_values(array_filter($overallLatestRunIdsBySourceId->values()->all())))
            ->get()
            ->keyBy('source_id');

        $activeIssuesBySourceId = DatasetIssue::query()
            ->active()
            ->whereIn('monitored_source_id', $sourceIds)
            ->whereIn('dataset_comparison_run_id', $periodLatestRunIds)
            ->orderBy('monitored_source_id')
            ->orderByDesc('severity')
            ->orderBy('id')
            ->get()
            ->groupBy('monitored_source_id');

        $sources = [];

        foreach ($runs as $periodLatestRun) {
            $source = $periodLatestRun->source;
            if ($source === null) {
                continue;
            }

            $sourceId = (int) $source->id;
            $overallLatestRun = $overallLatestRuns->get($sourceId);
            $activeIssues = $activeIssuesBySourceId->get($sourceId, collect());

            $added = 0;
            $removed = 0;
            $changed = 0;
            $unchanged = 0;

            if ($periodLatestRun->status === 'completed' && is_array($periodLatestRun->summary)) {
                $added = (int) ($periodLatestRun->summary['added'] ?? 0);
                $removed = (int) ($periodLatestRun->summary['removed'] ?? 0);
                $changed = (int) ($periodLatestRun->summary['changed'] ?? 0);
                $unchanged = (int) ($periodLatestRun->summary['unchanged'] ?? 0);
            }

            $severityCounts = $activeIssues
                ->groupBy('severity')
                ->map(fn ($group) => $group->count())
                ->all();

            $issues = [];
            foreach ($activeIssues as $issue) {
                $issues[] = $this->buildIssueLine($issue, $overallLatestRun);
            }

            $sources[] = new DailyMonitoringSummarySourceSection(
                name: (string) $source->name,
                sourceKey: (string) $source->key,
                periodRunId: (int) $periodLatestRun->id,
                periodRunStatus: (string) $periodLatestRun->status,
                periodRunFinishedAt: DisplayTimestamp::format($periodLatestRun->finished_at),
                overallRunId: $overallLatestRun !== null && (int) $overallLatestRun->id !== (int) $periodLatestRun->id
                    ? (int) $overallLatestRun->id
                    : null,
                overallRunStatus: $overallLatestRun !== null && (int) $overallLatestRun->id !== (int) $periodLatestRun->id
                    ? (string) $overallLatestRun->status
                    : null,
                overallRunFinishedAt: $overallLatestRun !== null && (int) $overallLatestRun->id !== (int) $periodLatestRun->id
                    ? DisplayTimestamp::format($overallLatestRun->finished_at)
                    : null,
                recoveryNote: $this->recoveryNote($periodLatestRun, $overallLatestRun),
                added: $added,
                removed: $removed,
                changed: $changed,
                unchanged: $unchanged,
                activeIssueCount: $activeIssues->count(),
                errorCount: (int) ($severityCounts['error'] ?? 0),
                warningCount: (int) ($severityCounts['warning'] ?? 0),
                infoCount: (int) ($severityCounts['info'] ?? 0),
                issues: $issues,
            );
        }

        return new DailyMonitoringSummaryReport(
            emptyMessage: null,
            periodLabel: sprintf(
                'Period: last %d hour(s) (since %s)',
                $hours,
                DisplayTimestamp::format($cutoff),
            ),
            sources: $sources,
        );
    }

    /**
     * @return Collection<int, int|null>
     */
    private function periodLatestRunIdsBySourceId(Carbon $cutoff): Collection
    {
        return DatasetComparisonRun::query()
            ->where(function ($q) use ($cutoff): void {
                $q->where(function ($q2) use ($cutoff): void {
                    $q2->whereNotNull('started_at')
                        ->where('started_at', '>=', $cutoff);
                })->orWhere(function ($q2) use ($cutoff): void {
                    $q2->whereNull('started_at')
                        ->where('created_at', '>=', $cutoff);
                });
            })
            ->select('source_id', DB::raw('max(id) as latest_run_id'))
            ->groupBy('source_id')
            ->pluck('latest_run_id', 'source_id');
    }

    private function recoveryNote(
        DatasetComparisonRun $periodLatestRun,
        ?DatasetComparisonRun $overallLatestRun,
    ): string {
        if ($overallLatestRun === null) {
            return '';
        }

        if ((int) $overallLatestRun->id === (int) $periodLatestRun->id) {
            return '';
        }

        if ($periodLatestRun->status !== 'failed') {
            return '';
        }

        if (! in_array($overallLatestRun->status, ['completed', 'baseline'], true)) {
            return '';
        }

        return sprintf(
            'Note: latest run in this period failed, but the source has since recovered (overall latest run #%d %s).',
            (int) $overallLatestRun->id,
            (string) $overallLatestRun->status,
        );
    }

    private function buildIssueLine(
        DatasetIssue $issue,
        ?DatasetComparisonRun $overallLatestRun,
    ): DailyMonitoringSummaryIssueLine {
        return new DailyMonitoringSummaryIssueLine(
            severity: (string) $issue->severity,
            issueType: $issue->issue_type !== null && $issue->issue_type !== ''
                ? (string) $issue->issue_type
                : null,
            message: trim((string) $issue->message),
            transition: IssueChangeDetail::transitionLabel($issue),
            suffix: $issue->issue_type === SourceRunFailedIssueService::ISSUE_TYPE
                ? $this->sourceRunFailedSuffix($issue, $overallLatestRun)
                : '',
        );
    }

    private function sourceRunFailedSuffix(
        DatasetIssue $issue,
        ?DatasetComparisonRun $overallLatestRun,
    ): string {
        if ($overallLatestRun === null) {
            return '';
        }

        if (
            in_array($overallLatestRun->status, ['completed', 'baseline'], true)
            && (int) $overallLatestRun->id > (int) $issue->dataset_comparison_run_id
        ) {
            return sprintf(
                '(recovered - overall latest run #%d %s)',
                (int) $overallLatestRun->id,
                (string) $overallLatestRun->status,
            );
        }

        if ($overallLatestRun->status === 'failed' && (int) $overallLatestRun->id === (int) $issue->dataset_comparison_run_id) {
            return '(still failing)';
        }

        return '';
    }
}
