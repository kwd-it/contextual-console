<?php

namespace App\Console\Commands;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DailySummaryCommand extends Command
{
    protected $signature = 'contextual-console:daily-summary
        {--hours=24 : Look back this many hours for runs}
        {--email : Email the summary to the configured recipient}';

    protected $description = 'Summarise recent monitoring results by monitored source (for future notifications).';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours <= 0) {
            $this->error('--hours must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subHours($hours);

        $latestRunIdsBySourceId = DatasetComparisonRun::query()
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

        $latestRunIds = array_values(array_unique(array_filter(
            $latestRunIdsBySourceId->values()->all(),
            fn ($id) => $id !== null
        )));

        if ($latestRunIds === []) {
            $summary = sprintf(
                'No monitoring runs found in the last %d hour(s) (since %s).',
                $hours,
                $this->formatTime($cutoff),
            );

            $this->line($summary);

            if ((bool) $this->option('email')) {
                $to = (string) config('contextual_console.daily_summary_to', '');
                if (trim($to) === '') {
                    $this->error('Daily summary email requested, but no recipient is configured. Set CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO.');

                    return self::FAILURE;
                }

                Mail::send((new \App\Mail\ContextualConsoleDailySummaryMail($summary))->to($to));
            }

            return self::SUCCESS;
        }

        $runs = DatasetComparisonRun::query()
            ->with('source')
            ->whereIn('id', $latestRunIds)
            ->orderBy('source_id')
            ->get();

        /** @var array<int, int> $issueCountsByRunId */
        $issueCountsByRunId = DatasetIssue::query()
            ->select('dataset_comparison_run_id', DB::raw('count(*) as total'))
            ->whereIn('dataset_comparison_run_id', $latestRunIds)
            ->groupBy('dataset_comparison_run_id')
            ->pluck('total', 'dataset_comparison_run_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        /** @var array<int, array<string, int>> $severityCountsByRunId */
        $severityCountsByRunId = [];

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

        $lines = [];
        $lines[] = 'Daily monitoring summary';
        $lines[] = sprintf(
            'Period: last %d hour(s) (since %s)',
            $hours,
            $this->formatTime($cutoff),
        );
        $lines[] = '';

        foreach ($runs as $run) {
            $source = $run->source;
            if ($source === null) {
                continue;
            }

            $added = 0;
            $removed = 0;
            $changed = 0;
            $unchanged = 0;

            if ($run->status === 'completed' && is_array($run->summary)) {
                $added = (int) ($run->summary['added'] ?? 0);
                $removed = (int) ($run->summary['removed'] ?? 0);
                $changed = (int) ($run->summary['changed'] ?? 0);
                $unchanged = (int) ($run->summary['unchanged'] ?? 0);
            }

            $issueCount = (int) ($issueCountsByRunId[(int) $run->id] ?? 0);
            $severityCounts = $severityCountsByRunId[(int) $run->id] ?? [];

            $lines[] = (string) $source->name;
            $lines[] = sprintf('Source key: %s', (string) $source->key);
            $lines[] = sprintf(
                'Latest run: #%d %s',
                (int) $run->id,
                (string) $run->status,
            );
            $lines[] = sprintf(
                'Changes: added=%d removed=%d changed=%d unchanged=%d',
                $added,
                $removed,
                $changed,
                $unchanged,
            );
            $lines[] = sprintf(
                'Issues: %d errors=%d warnings=%d info=%d',
                $issueCount,
                (int) ($severityCounts['error'] ?? 0),
                (int) ($severityCounts['warning'] ?? 0),
                (int) ($severityCounts['info'] ?? 0),
            );
            $lines[] = '';
        }

        $summary = implode(PHP_EOL, $lines);

        $this->line($summary);

        if ((bool) $this->option('email')) {
            $to = (string) config('contextual_console.daily_summary_to', '');
            if (trim($to) === '') {
                $this->error('Daily summary email requested, but no recipient is configured. Set CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO.');

                return self::FAILURE;
            }

            Mail::send((new \App\Mail\ContextualConsoleDailySummaryMail($summary))->to($to));
        }

        return self::SUCCESS;
    }

    private function formatTime(Carbon $t): string
    {
        return $t->toDateTimeString();
    }
}
