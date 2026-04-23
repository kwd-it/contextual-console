<?php

namespace App\Domains\Housebuilder\Services;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Illuminate\Support\Carbon;

class PlotDatasetRunService
{
    public function __construct(
        private PlotDatasetComparisonService $comparison,
        private PlotDatasetPresenceChangeLogger $presenceLogger,
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
            return DatasetComparisonRun::create([
                'source_id' => $source->id,
                'current_snapshot_id' => $currentSnapshot->id,
                'previous_snapshot_id' => null,
                'status' => 'baseline',
                'summary' => null,
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        }

        $summary = $this->comparison->compare(
            $previousSnapshot->payload ?? [],
            $currentSnapshot->payload ?? [],
        );

        $this->presenceLogger->logFromComparison($summary);

        return DatasetComparisonRun::create([
            'source_id' => $source->id,
            'current_snapshot_id' => $currentSnapshot->id,
            'previous_snapshot_id' => $previousSnapshot->id,
            'status' => 'completed',
            'summary' => $summary,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }
}

