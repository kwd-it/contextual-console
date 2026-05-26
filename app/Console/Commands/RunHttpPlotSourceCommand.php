<?php

namespace App\Console\Commands;

use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\HttpMonitoredSourceRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunHttpPlotSourceCommand extends Command
{
    protected $signature = 'contextual-console:run-http-plot-source
                            {sourceKey : Monitored source key (e.g. hb:foo)}';

    protected $description = 'Run a Housebuilder plot monitored source from an HTTP JSON endpoint configured on the source.';

    public function handle(HttpMonitoredSourceRunService $runner): int
    {
        $sourceKey = (string) $this->argument('sourceKey');

        $source = MonitoredSource::query()->where('key', $sourceKey)->first();
        if ($source === null) {
            $this->error("Monitored source not found for key: {$sourceKey}");

            return self::FAILURE;
        }

        if (! $runner->isEligible($source)) {
            $this->error("Monitored source '{$source->key}' is missing endpoint_url.");

            return self::FAILURE;
        }

        $run = $runner->run($source);

        if ($run->status === 'failed') {
            $this->error("Source run failed. run_id: {$run->id} status: failed");

            return self::FAILURE;
        }

        $this->line("source: {$source->key} ({$source->name})");
        $this->line("run_id: {$run->id}");
        $this->line("status: {$run->status}");
        $this->line("current_snapshot_id: {$run->current_snapshot_id}");
        $this->line('previous_snapshot_id: '.($run->previous_snapshot_id ?? 'none'));

        if ($run->status === 'completed' && $run->summary !== null) {
            $summary = (array) $run->summary;
            $this->line(sprintf(
                'summary: added=%d removed=%d changed=%d unchanged=%d',
                (int) ($summary['added'] ?? 0),
                (int) ($summary['removed'] ?? 0),
                (int) ($summary['changed'] ?? 0),
                (int) ($summary['unchanged'] ?? 0),
            ));
        }

        $issueQuery = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id);
        $issueCount = (int) $issueQuery->count();
        $this->line("Issues: {$issueCount}");

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

        return self::SUCCESS;
    }
}
