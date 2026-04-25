<?php

namespace App\Console\Commands;

use App\Core\Services\MonitoredSourceStatusService;
use Illuminate\Console\Command;

class SourceStatusCommand extends Command
{
    protected $signature = 'contextual-console:source-status';

    protected $description = 'Show latest run status and issue counts for all monitored sources.';

    public function handle(MonitoredSourceStatusService $status): int
    {
        $summaries = $status->summaries();

        if ($summaries === []) {
            $this->line('No monitored sources found.');

            return self::SUCCESS;
        }

        foreach ($summaries as $i => $summary) {
            if ($i > 0) {
                $this->line('');
            }

            $this->line("Source: {$summary['source_name']}");
            $this->line("Key: {$summary['source_key']}");

            if ($summary['latest_run_id'] === null) {
                $this->line('Latest run: none');
                $this->line('Issues: 0');
                continue;
            }

            $this->line("Latest run: {$summary['latest_run_status']}");
            $this->line("Run ID: {$summary['latest_run_id']}");

            if ($summary['latest_run_finished_at'] !== null) {
                $this->line('Finished: '.$summary['latest_run_finished_at']->format('Y-m-d H:i:s'));
            }

            $this->line(sprintf(
                'Summary: added=%d removed=%d changed=%d unchanged=%d',
                $summary['added'],
                $summary['removed'],
                $summary['changed'],
                $summary['unchanged'],
            ));

            $this->line("Issues: {$summary['issue_count']}");

            foreach (['error', 'warning', 'info'] as $severity) {
                $key = "{$severity}_count";
                $count = (int) ($summary[$key] ?? 0);
                if ($count > 0) {
                    $this->line("- {$severity}: {$count}");
                }
            }
        }

        return self::SUCCESS;
    }
}
