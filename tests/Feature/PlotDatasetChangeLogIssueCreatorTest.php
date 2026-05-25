<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a warning issue when a plot is removed from the source (presence new_value null)', function () {
    $source = MonitoredSource::create(['key' => 'hb:cl-issue-presence', 'name' => 'ChangeLog Presence']);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 2, 'price' => 200_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'], // plot 2 removed
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);

    $issue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('entity_type', 'plot')
        ->where('entity_id', '2')
        ->where('field', 'presence')
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE)
        ->firstOrFail();

    expect($issue->severity)->toBe('warning');
    expect($issue->context)->toHaveKeys(['change_log_id', 'field', 'old_value', 'new_value']);
    expect($issue->context['field'])->toBe('presence');
    expect($issue->context['new_value'])->toBeNull();
});

it('creates an info issue for status change', function () {
    $source = MonitoredSource::create(['key' => 'hb:cl-issue-status', 'name' => 'ChangeLog Status']);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 100_000, 'status' => 'reserved'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);

    $issue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('entity_type', 'plot')
        ->where('entity_id', '1')
        ->where('field', 'status')
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED)
        ->firstOrFail();

    expect($issue->severity)->toBe('info');
    expect($issue->context)->toHaveKeys(['change_log_id', 'field', 'old_value', 'new_value']);
    expect($issue->context['field'])->toBe('status');
    expect($issue->context['old_value'])->toBe('available');
    expect($issue->context['new_value'])->toBe('reserved');
});

it('creates an info issue for price change', function () {
    $source = MonitoredSource::create(['key' => 'hb:cl-issue-price', 'name' => 'ChangeLog Price']);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);

    $issue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('entity_type', 'plot')
        ->where('entity_id', '1')
        ->where('field', 'price')
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED)
        ->firstOrFail();

    expect($issue->severity)->toBe('info');
    expect($issue->context)->toHaveKeys(['change_log_id', 'field', 'old_value', 'new_value']);
    expect($issue->context['field'])->toBe('price');
    expect($issue->context['old_value'])->toBe('100000');
    expect($issue->context['new_value'])->toBe('110000');
});

it('does not create issues for newly tracked plot fields (title, bedrooms, development, house_type, url)', function () {
    $source = MonitoredSource::create(['key' => 'hb:cl-issue-new-fields', 'name' => 'ChangeLog New Fields']);

    $baseline = [
        [
            'id' => 1,
            'price' => 100_000,
            'status' => 'available',
            'title' => 'Plot 12 - The Oakwood',
            'bedrooms' => 3,
            'development' => 'Maple Fields',
            'house_type' => 'Detached',
            'url' => 'https://example.test/plots/12',
        ],
    ];

    $second = [
        [
            'id' => 1,
            'price' => 100_000,  // unchanged: no info issue
            'status' => 'available', // unchanged: no info issue
            'title' => 'Plot 12 - The Oakwood (Show home)',
            'bedrooms' => 4,
            'development' => 'Maple Fields - Phase 2',
            'house_type' => 'Semi-detached',
            'url' => 'https://example.test/plots/12-renamed',
        ],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);

    foreach (['title', 'bedrooms', 'development', 'house_type', 'url'] as $field) {
        expect(DatasetIssue::query()
            ->where('dataset_comparison_run_id', $run2->id)
            ->where('field', $field)
            ->exists())->toBeFalse();
    }

    expect(DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->whereIn('issue_type', [
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE,
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED,
            PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED,
        ])
        ->count())->toBe(0);
});

it('does not create duplicate issues if run twice for the same dataset comparison run', function () {
    $source = MonitoredSource::create(['key' => 'hb:cl-issue-dedupe', 'name' => 'ChangeLog Dedupe']);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // 2 change logs => 2 issues
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);

    $issuesBefore = DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->count();

    app(PlotDatasetChangeLogIssueCreator::class)->createForRun(DatasetComparisonRun::query()->findOrFail($run2->id));

    $issuesAfter = DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->count();

    expect($issuesAfter)->toBe($issuesBefore);
});
