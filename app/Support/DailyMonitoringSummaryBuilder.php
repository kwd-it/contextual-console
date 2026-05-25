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
            return sprintf(
                'No monitoring runs found in the last %d hour(s) (since %s).',
                $hours,
                DisplayTimestamp::format($cutoff),
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

        $lines = [];
        $lines[] = 'Daily monitoring summary';
        $lines[] = sprintf(
            'Period: last %d hour(s) (since %s)',
            $hours,
            DisplayTimestamp::format($cutoff),
        );
        $lines[] = '';

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

            $lines[] = (string) $source->name;
            $lines[] = sprintf('Source key: %s', (string) $source->key);
            $lines[] = sprintf(
                'Latest run in period: #%d %s',
                (int) $periodLatestRun->id,
                (string) $periodLatestRun->status,
            );
            $lines[] = sprintf(
                '  finished %s',
                DisplayTimestamp::format($periodLatestRun->finished_at),
            );

            if ($overallLatestRun !== null && (int) $overallLatestRun->id !== (int) $periodLatestRun->id) {
                $lines[] = sprintf(
                    'Overall latest run: #%d %s (finished %s)',
                    (int) $overallLatestRun->id,
                    (string) $overallLatestRun->status,
                    DisplayTimestamp::format($overallLatestRun->finished_at),
                );
            }

            $recoveryNote = $this->recoveryNote($periodLatestRun, $overallLatestRun);
            if ($recoveryNote !== '') {
                $lines[] = $recoveryNote;
            }

            $lines[] = sprintf(
                'Changes: added=%d removed=%d changed=%d unchanged=%d',
                $added,
                $removed,
                $changed,
                $unchanged,
            );
            $lines[] = sprintf(
                'Active issues: %d errors=%d warnings=%d info=%d',
                $activeIssues->count(),
                (int) ($severityCounts['error'] ?? 0),
                (int) ($severityCounts['warning'] ?? 0),
                (int) ($severityCounts['info'] ?? 0),
            );

            foreach ($activeIssues as $issue) {
                $lines[] = $this->formatIssueLine($issue, $overallLatestRun);
            }

            $lines[] = '';
        }

        return rtrim(implode(PHP_EOL, $lines));
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

    private function formatIssueLine(DatasetIssue $issue, ?DatasetComparisonRun $overallLatestRun): string
    {
        $label = sprintf('  - [%s]', (string) $issue->severity);

        if ($issue->issue_type !== null && $issue->issue_type !== '') {
            $label .= ' '.$issue->issue_type.':';
        }

        $label .= ' '.trim((string) $issue->message);

        $transition = IssueChangeDetail::transitionLabel($issue);
        if ($transition !== null) {
            $label .= ' ('.$transition.')';
        }

        if ($issue->issue_type === SourceRunFailedIssueService::ISSUE_TYPE) {
            $suffix = $this->sourceRunFailedSuffix($issue, $overallLatestRun);
            if ($suffix !== '') {
                $label .= ' '.$suffix;
            }
        }

        return $label;
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
