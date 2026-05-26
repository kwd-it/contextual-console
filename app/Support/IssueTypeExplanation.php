<?php

namespace App\Support;

use App\Core\Models\DatasetIssue;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetIssueDetector;

final class IssueTypeExplanation
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
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED => 'The plot status changed compared to the previous daily snapshot.',
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED => 'The plot price changed compared to the previous daily snapshot.',
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE => 'A plot that existed in the previous snapshot is no longer present in the source data.',
            PlotDatasetIssueDetector::ISSUE_TYPE_MISSING_REQUIRED_FIELD => 'A required plot field is missing from the source payload.',
            PlotDatasetIssueDetector::ISSUE_TYPE_DUPLICATE_VALUE => 'The same plot id appears more than once in the source payload.',
            PlotDatasetIssueDetector::ISSUE_TYPE_INVALID_VALUE => 'A plot field value failed validation rules.',
            PlotDatasetIssueDetector::ISSUE_TYPE_INVALID_RECORD => 'A plot payload item is not a valid record object.',
            SourceRunFailedIssueService::ISSUE_TYPE => 'The scheduled or manual source run did not complete successfully.',
            default => null,
        };
    }
}
