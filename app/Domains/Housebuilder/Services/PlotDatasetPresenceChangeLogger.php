<?php

namespace App\Domains\Housebuilder\Services;

/**
 * Logs plot additions/removals (dataset presence) using the stable domain change log contract.
 */
class PlotDatasetPresenceChangeLogger
{
    public function __construct(
        private ChangeDetectionService $changeDetection,
    ) {}

    /**
     * @param  array{
     *   added_ids?: array<int, int|string>,
     *   removed_ids?: array<int, int|string>
     * }  $comparison
     */
    public function logFromComparison(array $comparison, ?int $datasetComparisonRunId = null): void
    {
        foreach (($comparison['added_ids'] ?? []) as $plotId) {
            $this->changeDetection->recordDomainField('plot', $plotId, 'presence', null, 'present', $datasetComparisonRunId);
        }

        foreach (($comparison['removed_ids'] ?? []) as $plotId) {
            $this->changeDetection->recordDomainField('plot', $plotId, 'presence', 'present', null, $datasetComparisonRunId);
        }
    }
}
