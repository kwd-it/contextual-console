<?php

namespace App\Core\Services;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Domains\Housebuilder\Services\PlotHttpIngestNormalizer;
use Illuminate\Support\Carbon;
use RuntimeException;

final class HttpMonitoredSourceRunService
{
    public function __construct(
        private HttpJsonSourceFetcher $fetcher,
        private PlotHttpIngestNormalizer $payloadNormalizer,
        private PlotDatasetRunService $plotDatasetRunService,
    ) {}

    public function isEligible(MonitoredSource $source): bool
    {
        return trim((string) ($source->endpoint_url ?? '')) !== '';
    }

    /**
     * Fetch live HTTP plot data for the source, compare it, and return the resulting run.
     * On fetch/normalize failure, records a failed run and source_run_failed issue (same as scheduled runs).
     */
    public function run(MonitoredSource $source): DatasetComparisonRun
    {
        $startedAt = now();

        try {
            $payload = $this->payloadNormalizer->normalize($source, $this->fetcher->fetch($source));
            $run = $this->plotDatasetRunService->run($source, $payload);
            $run->refresh();

            return $run;
        } catch (RuntimeException $e) {
            return $this->recordFailedRun($source, $startedAt, $e);
        }
    }

    private function recordFailedRun(MonitoredSource $source, Carbon $startedAt, RuntimeException $e): DatasetComparisonRun
    {
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
            'message' => "Source run failed for {$source->key}.",
            'context' => [
                'exception_message' => $e->getMessage(),
            ],
        ]);

        return $failedRun;
    }
}
