<?php

namespace App\Domains\Housebuilder\Services;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Illuminate\Support\Carbon;

class PlotDatasetRunService
{
    public function __construct(
        private PlotDatasetComparisonService $comparison,
        private PlotDatasetPresenceChangeLogger $presenceLogger,
        private PlotDatasetIssueDetector $issueDetector,
    ) {}

    /**
     * Persist a new snapshot, compare to previous snapshot for the same source (if any),
     * log changes via existing services, and store a run summary.
     *
     * @param  array<int, array<string, mixed>>  $payload
     */
    public function run(MonitoredSource $source, array $payload, ?Carbon $capturedAt = null): DatasetComparisonRun
    {
        $startedAt = now();

        $previousSnapshot = DatasetSnapshot::query()
            ->where('source_id', $source->id)
            ->latest('id')
            ->first();

        $currentSnapshot = DatasetSnapshot::create([
            'source_id' => $source->id,
            'payload' => $payload,
            'captured_at' => $capturedAt,
        ]);

        if ($previousSnapshot === null) {
            $run = DatasetComparisonRun::create([
                'source_id' => $source->id,
                'current_snapshot_id' => $currentSnapshot->id,
                'previous_snapshot_id' => null,
                'status' => 'baseline',
                'summary' => null,
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            $this->persistIssues($source, $currentSnapshot, $run, $payload);

            return $run;
        }

        $previousComparable = $this->comparablePayload($previousSnapshot->payload ?? []);
        $currentComparable = $this->comparablePayload($payload);

        $summary = $this->comparison->compare(
            $previousComparable,
            $currentComparable,
        );

        $this->presenceLogger->logFromComparison($summary);

        $run = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'current_snapshot_id' => $currentSnapshot->id,
            'previous_snapshot_id' => $previousSnapshot->id,
            'status' => 'completed',
            'summary' => $summary,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);

        $this->persistIssues($source, $currentSnapshot, $run, $payload);

        return $run;
    }

    /**
     * Prepare a payload safe for id-keyed comparison by filtering out records that cannot be compared:
     * - non-array items
     * - array items with missing/null/empty id
     *
     * @param  array<int, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function comparablePayload(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($item) => is_array($item))
            ->filter(function (array $item): bool {
                if (! array_key_exists('id', $item)) {
                    return false;
                }

                $id = $item['id'];
                if ($id === null) {
                    return false;
                }

                return ! (is_string($id) && trim($id) === '');
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $payload
     */
    private function persistIssues(MonitoredSource $source, DatasetSnapshot $snapshot, DatasetComparisonRun $run, array $payload): void
    {
        $issues = $this->issueDetector->detect($payload);

        foreach ($issues as $issue) {
            DatasetIssue::create([
                'monitored_source_id' => $source->id,
                'dataset_snapshot_id' => $snapshot->id,
                'dataset_comparison_run_id' => $run->id,
                'entity_type' => $issue['entity_type'] ?? null,
                'entity_id' => $issue['entity_id'] ?? null,
                'field' => $issue['field'] ?? null,
                'issue_type' => $issue['issue_type'],
                'severity' => $issue['severity'],
                'message' => $issue['message'],
                'context' => $issue['context'] ?? null,
            ]);
        }
    }
}

