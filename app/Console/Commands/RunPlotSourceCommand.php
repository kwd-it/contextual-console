<?php

namespace App\Console\Commands;

use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunPlotSourceCommand extends Command
{
    protected $signature = 'contextual-console:run-plot-source
                            {sourceKey : Monitored source key (e.g. hb:foo)}
                            {--file= : Path to a JSON payload file}';

    protected $description = 'Run a Housebuilder plot monitored source from a supplied JSON payload file.';

    public function handle(PlotDatasetRunService $service): int
    {
        $sourceKey = (string) $this->argument('sourceKey');
        $file = (string) ($this->option('file') ?? '');

        if ($file === '') {
            $this->error('Missing required option: --file=/path/to/payload.json');

            return self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error("Payload file not found: {$file}");

            return self::FAILURE;
        }

        $json = file_get_contents($file);
        if ($json === false) {
            $this->error("Unable to read payload file: {$file}");

            return self::FAILURE;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON payload: '.json_last_error_msg());

            return self::FAILURE;
        }

        if (! is_array($decoded)) {
            $this->error('Invalid JSON payload: expected a JSON array at the top level.');

            return self::FAILURE;
        }

        $source = MonitoredSource::query()->where('key', $sourceKey)->first();
        if ($source === null) {
            $this->error("Monitored source not found for key: {$sourceKey}");

            return self::FAILURE;
        }

        $run = $service->run($source, $decoded);
        $run->refresh();

        $this->line("source: {$source->key} ({$source->name})");
        $this->line("run_id: {$run->id}");
        $this->line("status: {$run->status}");
        $this->line("current_snapshot_id: {$run->current_snapshot_id}");
        $this->line('previous_snapshot_id: '.($run->previous_snapshot_id ?? 'none'));

        if ($run->status === 'completed' && is_array($run->summary)) {
            $added = $run->summary['added'] ?? null;
            $removed = $run->summary['removed'] ?? null;
            $changed = $run->summary['changed'] ?? null;
            $unchanged = $run->summary['unchanged'] ?? null;

            $this->line("summary: added={$added} removed={$removed} changed={$changed} unchanged={$unchanged}");
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

