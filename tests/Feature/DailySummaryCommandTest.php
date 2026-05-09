<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('reports recent runs grouped by monitored source', function () {
    $sourceA = MonitoredSource::create(['key' => 'hb:sum-a', 'name' => 'Summary A']);
    $sourceB = MonitoredSource::create(['key' => 'hb:sum-b', 'name' => 'Summary B']);

    $snapshotA = DatasetSnapshot::create(['source_id' => $sourceA->id, 'payload' => []]);
    $snapshotB = DatasetSnapshot::create(['source_id' => $sourceB->id, 'payload' => []]);

    $runA = DatasetComparisonRun::create([
        'source_id' => $sourceA->id,
        'current_snapshot_id' => $snapshotA->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 1, 'removed' => 2, 'changed' => 3, 'unchanged' => 4],
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHours(2)->addMinute(),
    ]);

    $runB = DatasetComparisonRun::create([
        'source_id' => $sourceB->id,
        'current_snapshot_id' => $snapshotB->id,
        'previous_snapshot_id' => null,
        'status' => 'baseline',
        'summary' => null,
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $sourceA->id,
        'dataset_snapshot_id' => $snapshotA->id,
        'dataset_comparison_run_id' => $runA->id,
        'issue_type' => 'test',
        'severity' => 'error',
        'message' => 'boom',
        'context' => null,
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $sourceA->id,
        'dataset_snapshot_id' => $snapshotA->id,
        'dataset_comparison_run_id' => $runA->id,
        'issue_type' => 'test',
        'severity' => 'warning',
        'message' => 'warn',
        'context' => null,
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Daily monitoring summary (last 24 hour(s)');

    expect($output)->toContain('source_name="Summary A"');
    expect($output)->toContain("source_key={$sourceA->key}");
    expect($output)->toContain("latest_run_id={$runA->id}");
    expect($output)->toContain('status=completed');
    expect($output)->toContain('added=1 removed=2 changed=3 unchanged=4');
    expect($output)->toContain('issues=2 error=1 warning=1 info=0');

    expect($output)->toContain('source_name="Summary B"');
    expect($output)->toContain("source_key={$sourceB->key}");
    expect($output)->toContain("latest_run_id={$runB->id}");
    expect($output)->toContain('status=baseline');
    expect($output)->toContain('added=0 removed=0 changed=0 unchanged=0');
    expect($output)->toContain('issues=0 error=0 warning=0 info=0');
});

it('excludes older runs outside the lookback window', function () {
    $source = MonitoredSource::create(['key' => 'hb:old', 'name' => 'Old Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);

    $oldRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 9, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHours(30),
        'finished_at' => now()->subHours(30)->addMinute(),
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('No monitoring runs found in the last 24 hour(s)');
    expect($output)->not->toContain("latest_run_id={$oldRun->id}");
    expect($output)->not->toContain("source_key={$source->key}");
});

it('handles no recent runs', function () {
    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('No monitoring runs found in the last 24 hour(s)');
});

it('--hours adjusts the lookback window', function () {
    $source = MonitoredSource::create(['key' => 'hb:hours', 'name' => 'Hours Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 1, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHours(36),
        'finished_at' => now()->subHours(36)->addMinute(),
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('No monitoring runs found in the last 24 hour(s)');
    expect($output)->not->toContain("latest_run_id={$run->id}");

    $exitCode2 = Artisan::call('contextual-console:daily-summary', ['--hours' => 48]);
    $output2 = Artisan::output();

    expect($exitCode2)->toBe(0);
    expect($output2)->toContain('Daily monitoring summary (last 48 hour(s)');
    expect($output2)->toContain("source_key={$source->key}");
    expect($output2)->toContain("latest_run_id={$run->id}");
});
