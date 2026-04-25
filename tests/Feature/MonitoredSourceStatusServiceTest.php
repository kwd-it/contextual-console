<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\MonitoredSourceStatusService;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @param  array<int, array<string, mixed>>  $summaries
 * @return array<string, mixed>
 */
function summaryFor(array $summaries, string $sourceKey): array
{
    foreach ($summaries as $summary) {
        if (($summary['source_key'] ?? null) === $sourceKey) {
            return $summary;
        }
    }

    throw new RuntimeException("Summary not found for key: {$sourceKey}");
}

it('returns a monitored source with no runs', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:no-runs',
        'name' => 'No Runs Source',
    ]);

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = summaryFor($summaries, $source->key);

    expect($summary['source_id'])->toBe($source->id);
    expect($summary['source_key'])->toBe($source->key);
    expect($summary['source_name'])->toBe($source->name);

    expect($summary['latest_run_id'])->toBeNull();
    expect($summary['latest_run_status'])->toBeNull();

    expect($summary['added'])->toBe(0);
    expect($summary['removed'])->toBe(0);
    expect($summary['changed'])->toBe(0);
    expect($summary['unchanged'])->toBe(0);

    expect($summary['issue_count'])->toBe(0);
    expect($summary['error_count'])->toBe(0);
    expect($summary['warning_count'])->toBe(0);
    expect($summary['info_count'])->toBe(0);
});

it('returns latest baseline run status', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:baseline-status',
        'name' => 'Baseline Status Source',
    ]);

    $payload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $run = app(PlotDatasetRunService::class)->run($source, $payload);
    $run->refresh();

    expect($run->status)->toBe('baseline');

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = summaryFor($summaries, $source->key);

    expect($summary['latest_run_id'])->toBe($run->id);
    expect($summary['latest_run_status'])->toBe('baseline');
    expect($summary['current_snapshot_id'])->toBeInt();
    expect($summary['previous_snapshot_id'])->toBeNull();

    expect($summary['added'])->toBe(0);
    expect($summary['removed'])->toBe(0);
    expect($summary['changed'])->toBe(0);
    expect($summary['unchanged'])->toBe(0);
});

it('returns latest completed run summary counts', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:completed-summary',
        'name' => 'Completed Summary Source',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $changed = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // added
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $changed);
    $run2->refresh();

    expect($run2->status)->toBe('completed');
    expect($run2->summary)->toBeArray();

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = summaryFor($summaries, $source->key);

    expect($summary['latest_run_status'])->toBe('completed');
    expect($summary['latest_run_id'])->toBe($run2->id);

    expect($summary['added'])->toBe((int) ($run2->summary['added'] ?? 0));
    expect($summary['removed'])->toBe((int) ($run2->summary['removed'] ?? 0));
    expect($summary['changed'])->toBe((int) ($run2->summary['changed'] ?? 0));
    expect($summary['unchanged'])->toBe((int) ($run2->summary['unchanged'] ?? 0));
});

it('returns issue counts for the latest run', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:latest-issues',
        'name' => 'Latest Issues Source',
    ]);

    $invalidPayload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 2, 'price' => null, 'status' => 'pending'], // warning: missing price + invalid status
        ['id' => 2, 'price' => -1, 'status' => 'sold'], // error: duplicate id + warning: invalid price
        'bad-record', // error: invalid_record
    ];

    $run = app(PlotDatasetRunService::class)->run($source, $invalidPayload);
    $run->refresh();

    $expectedTotal = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->count();
    $expectedErrors = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->where('severity', 'error')->count();
    $expectedWarnings = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->where('severity', 'warning')->count();
    $expectedInfos = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->where('severity', 'info')->count();

    expect($expectedTotal)->toBeGreaterThan(0);
    expect($expectedErrors)->toBeGreaterThan(0);
    expect($expectedWarnings)->toBeGreaterThan(0);
    expect($expectedInfos)->toBe(0);

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = summaryFor($summaries, $source->key);

    expect($summary['issue_count'])->toBe($expectedTotal);
    expect($summary['error_count'])->toBe($expectedErrors);
    expect($summary['warning_count'])->toBe($expectedWarnings);
    expect($summary['info_count'])->toBe($expectedInfos);
});

it('does not count issues from older runs', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:no-old-issues',
        'name' => 'No Old Issues Source',
    ]);

    $invalidBaseline = [
        'bad-record',
        ['price' => 100_000, 'status' => 'available'], // missing id (issue)
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $validSecond = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
        ['id' => 2, 'price' => 200_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $run1 = $service->run($source, $invalidBaseline);
    $run2 = $service->run($source, $validSecond);

    $run1->refresh();
    $run2->refresh();

    expect(DatasetIssue::query()->where('dataset_comparison_run_id', $run1->id)->count())->toBeGreaterThan(0);
    expect(DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->count())->toBe(0);

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = summaryFor($summaries, $source->key);

    expect($summary['latest_run_id'])->toBe($run2->id);
    expect($summary['latest_run_status'])->toBe('completed');
    expect($summary['issue_count'])->toBe(0);
    expect($summary['error_count'])->toBe(0);
    expect($summary['warning_count'])->toBe(0);
    expect($summary['info_count'])->toBe(0);
});

it('handles multiple sources independently', function () {
    $sourceA = MonitoredSource::create(['key' => 'hb:multi-a', 'name' => 'Multi A']);
    $sourceB = MonitoredSource::create(['key' => 'hb:multi-b', 'name' => 'Multi B']);

    $service = app(PlotDatasetRunService::class);

    $runA = $service->run($sourceA, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $runA->refresh();

    $service->run($sourceB, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $runB2 = $service->run($sourceB, [
        'bad-record',
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ]);
    $runB2->refresh();

    $summaries = app(MonitoredSourceStatusService::class)->summaries();

    $a = summaryFor($summaries, $sourceA->key);
    $b = summaryFor($summaries, $sourceB->key);

    expect($a['latest_run_id'])->toBe($runA->id);
    expect($a['latest_run_status'])->toBe('baseline');
    expect($a['issue_count'])->toBe(0);

    expect($b['latest_run_id'])->toBe($runB2->id);
    expect($b['latest_run_status'])->toBe('completed');
    expect($b['issue_count'])->toBeGreaterThan(0);

    // Sanity: latest run IDs should differ and not bleed across sources
    expect($a['latest_run_id'])->not->toBe($b['latest_run_id']);
});
