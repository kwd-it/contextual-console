<?php

use App\Core\Models\DatasetIssue;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetIssueDetector;
use App\Support\IssueTypeSuggestedCheck;

it('returns suggested checks for known issue types', function (string $issueType, string $expected) {
    expect(IssueTypeSuggestedCheck::forType($issueType))->toBe($expected);
})->with([
    [PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED, 'Check whether this status movement matches expected sales activity.'],
    [PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED, 'Check whether this price change matches expected sales or pricing updates.'],
    [PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE, 'Check whether the plot was deliberately unpublished, drafted, sold out, or removed upstream.'],
    [PlotDatasetIssueDetector::ISSUE_TYPE_MISSING_REQUIRED_FIELD, 'Check the source payload, then compare the WordPress admin value and raw post meta if the admin screen looks correct.'],
    [PlotDatasetIssueDetector::ISSUE_TYPE_DUPLICATE_VALUE, 'Check the source payload for duplicate plot ids and confirm which record should be kept.'],
    [PlotDatasetIssueDetector::ISSUE_TYPE_INVALID_VALUE, 'Check whether the source value is genuinely invalid or whether the upstream mapping needs attention.'],
    [PlotDatasetIssueDetector::ISSUE_TYPE_INVALID_RECORD, 'Check the source payload item structure and whether upstream is sending a valid plot object.'],
    [SourceRunFailedIssueService::ISSUE_TYPE, 'Check the failed run details first. If the source later recovered, this may already be resolved.'],
]);

it('returns null for unknown issue types', function () {
    expect(IssueTypeSuggestedCheck::forType('unknown_issue_type'))->toBeNull();
});

it('returns null when issue type is missing', function () {
    $issue = new DatasetIssue(['issue_type' => null]);

    expect(IssueTypeSuggestedCheck::forIssue($issue))->toBeNull();
});
