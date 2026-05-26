<?php

namespace App\Support;

final class DailyMonitoringSummarySourceSection
{
    /**
     * @param  list<DailyMonitoringSummaryIssueLine>  $issues
     */
    public function __construct(
        public readonly string $name,
        public readonly string $sourceKey,
        public readonly ?string $sourceShowUrl = null,
        public readonly ?string $periodRunShowUrl = null,
        public readonly ?string $overallRunShowUrl = null,
        public readonly int $periodRunId,
        public readonly string $periodRunStatus,
        public readonly string $periodRunFinishedAt,
        public readonly ?int $overallRunId,
        public readonly ?string $overallRunStatus,
        public readonly ?string $overallRunFinishedAt,
        public readonly string $recoveryNote,
        public readonly int $added,
        public readonly int $removed,
        public readonly int $changed,
        public readonly int $unchanged,
        public readonly int $activeIssueCount,
        public readonly int $errorCount,
        public readonly int $warningCount,
        public readonly int $infoCount,
        public readonly array $issues,
    ) {}
}
