<?php

namespace App\Core\Services;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;

final class SourceRunFailedIssueService
{
    public const ISSUE_TYPE = 'source_run_failed';

    /**
     * Mark open source_run_failed issues from earlier failed runs as resolved after a successful run.
     */
    public function resolveSupersededForSuccessfulRun(MonitoredSource $source, DatasetComparisonRun $run): int
    {
        if (! in_array($run->status, ['completed', 'baseline'], true)) {
            return 0;
        }

        return DatasetIssue::query()
            ->where('monitored_source_id', $source->id)
            ->where('issue_type', self::ISSUE_TYPE)
            ->whereIn('status', DatasetIssue::ACTIVE_STATUSES)
            ->where('dataset_comparison_run_id', '<', $run->id)
            ->update(['status' => DatasetIssue::STATUS_RESOLVED]);
    }
}
