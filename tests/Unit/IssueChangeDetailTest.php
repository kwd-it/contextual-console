<?php

use App\Core\Models\DatasetIssue;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Support\IssueChangeDetail;
use Tests\TestCase;

uses(TestCase::class);

it('formats status change transitions from issue context', function () {
    $issue = new DatasetIssue([
        'issue_type' => PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED,
        'field' => 'status',
        'context' => [
            'field' => 'status',
            'old_value' => 'available',
            'new_value' => 'reserved',
        ],
    ]);

    expect(IssueChangeDetail::transitionLabel($issue))->toBe('available -> reserved');
});

it('formats price change transitions from issue context', function () {
    $issue = new DatasetIssue([
        'issue_type' => PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED,
        'field' => 'price',
        'context' => [
            'field' => 'price',
            'old_value' => '100000',
            'new_value' => '110000',
        ],
    ]);

    expect(IssueChangeDetail::transitionLabel($issue))->toBe('100000 -> 110000');
});

it('returns null for non change-driven issues', function () {
    $issue = new DatasetIssue([
        'issue_type' => 'invalid_plot_status',
        'context' => ['old_value' => 'a', 'new_value' => 'b'],
    ]);

    expect(IssueChangeDetail::transitionLabel($issue))->toBeNull();
});

it('uses the issue field column when context omits field', function () {
    $issue = new DatasetIssue([
        'issue_type' => PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED,
        'field' => 'status',
        'context' => [
            'old_value' => 'available',
            'new_value' => 'reserved',
        ],
    ]);

    expect(IssueChangeDetail::transitionLabel($issue))->toBe('available -> reserved');
});
