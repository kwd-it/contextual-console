<?php

namespace App\Console\Commands;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Core\Services\HttpJsonSourceFetcher;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Domains\Housebuilder\Services\PlotHttpIngestNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RunScheduledSourcesCommand extends Command
{
    protected $signature = 'contextual-console:run-scheduled-sources';

    protected $description = 'Run all monitored HTTP plot sources that have endpoint_url configured.';

    public function handle(HttpJsonSourceFetcher $fetcher, PlotHttpIngestNormalizer $payloadNormalizer, PlotDatasetRunService $service): int
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
            $startedAt = now();

            try {
                $payload = $payloadNormalizer->normalize($source, $fetcher->fetch($source));
                $run = $service->run($source, $payload);
                $run->refresh();

                $issueQuery = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id);
                $issueCount = (int) $issueQuery->count();

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
            } catch (RuntimeException $e) {
                $anyFailures = true;

                $previousSnapshot = DatasetSnapshot::query()
                    ->where('source_id', $source->id)
                    ->latest('id')
                    ->first();

                $failedRun = DatasetComparisonRun::create([
                    'source_id' => $source->id,
                    'current_snapshot_id' => null,
                    'previous_snapshot_id' => $previousSnapshot?->id,
                    'status' => 'failed',
                    'summary' => null,
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                ]);

                DatasetIssue::create([
                    'monitored_source_id' => $source->id,
                    'dataset_snapshot_id' => null,
                    'dataset_comparison_run_id' => $failedRun->id,
                    'entity_type' => null,
                    'entity_id' => null,
                    'field' => null,
                    'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
                    'severity' => 'error',
                    'message' => "Scheduled source run failed for {$source->key}.",
                    'context' => [
                        'exception_message' => $e->getMessage(),
                    ],
                ]);

                $this->error(sprintf(
                    'source=%s run_id=%d status=failed issues=1 (%s)',
                    (string) $source->key,
                    (int) $failedRun->id,
                    $e->getMessage(),
                ));
            }
        }

        return $anyFailures ? self::FAILURE : self::SUCCESS;
    }
}
