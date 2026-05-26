<?php

namespace App\Console\Commands;

use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\HttpMonitoredSourceRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunScheduledSourcesCommand extends Command
{
    protected $signature = 'contextual-console:run-scheduled-sources';

    protected $description = 'Run all monitored HTTP plot sources that have endpoint_url configured.';

    public function handle(HttpMonitoredSourceRunService $runner): int
    {
        $sources = MonitoredSource::query()
            ->whereNotNull('endpoint_url')
            ->where('endpoint_url', '!=', '')
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            $this->line('No eligible monitored HTTP plot sources found (no endpoint_url configured).');

            return self::SUCCESS;
        }

        $this->line(sprintf('Running %d monitored HTTP plot source(s)...', $sources->count()));

        $anyFailures = false;

        foreach ($sources as $source) {
            $run = $runner->run($source);

            $issueQuery = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id);
            $issueCount = (int) $issueQuery->count();

            if ($run->status === 'failed') {
                $anyFailures = true;

                $this->error(sprintf(
                    'source=%s run_id=%d status=failed issues=%d',
                    (string) $source->key,
                    (int) $run->id,
                    $issueCount,
                ));

                continue;
            }

            $this->line(sprintf(
                'source=%s run_id=%d status=%s issues=%d',
                (string) $source->key,
                (int) $run->id,
                (string) $run->status,
                $issueCount,
            ));

            if ($issueCount > 0) {
                $bySeverity = $issueQuery
                    ->select('severity', DB::raw('count(*) as total'))
                    ->groupBy('severity')
                    ->pluck('total', 'severity');

                foreach (['error', 'warning', 'info'] as $severity) {
                    $count = (int) ($bySeverity[$severity] ?? 0);
                    if ($count > 0) {
                        $this->line("- {$severity}: {$count}");
                    }
                }
            }
        }

        return $anyFailures ? self::FAILURE : self::SUCCESS;
    }
}
