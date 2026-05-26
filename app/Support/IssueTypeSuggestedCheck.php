<?php

namespace App\Support;

use App\Core\Models\DatasetIssue;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetIssueDetector;

final class IssueTypeSuggestedCheck
{
    public static function forIssue(DatasetIssue $issue): ?string
    {
        $type = $issue->issue_type;
        if ($type === null || $type === '') {
            return null;
        }

        return self::forType($type);
    }

    public static function forType(string $issueType): ?string
    {
        return match ($issueType) {
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED => 'Check whether this status movement matches expected sales activity.',
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED => 'Check whether this price change matches expected sales or pricing updates.',
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE => 'Check whether the plot was deliberately unpublished, drafted, sold out, or removed upstream.',
            PlotDatasetIssueDetector::ISSUE_TYPE_MISSING_REQUIRED_FIELD => 'Check the source payload, then compare the WordPress admin value and raw post meta if the admin screen looks correct.',
            PlotDatasetIssueDetector::ISSUE_TYPE_DUPLICATE_VALUE => 'Check the source payload for duplicate plot ids and confirm which record should be kept.',
            PlotDatasetIssueDetector::ISSUE_TYPE_INVALID_VALUE => 'Check whether the source value is genuinely invalid or whether the upstream mapping needs attention.',
            PlotDatasetIssueDetector::ISSUE_TYPE_INVALID_RECORD => 'Check the source payload item structure and whether upstream is sending a valid plot object.',
            SourceRunFailedIssueService::ISSUE_TYPE => 'Check the failed run details first. If the source later recovered, this may already be resolved.',
            default => null,
        };
    }
}
