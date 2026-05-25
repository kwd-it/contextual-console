<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Mail\ContextualConsoleDailySummaryMail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

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
    expect($output)->toContain('Daily monitoring summary');
    expect($output)->toContain('Period: last 24 hour(s)');

    expect($output)->toContain('Summary A');
    expect($output)->toContain("Source key: {$sourceA->key}");
    expect($output)->toContain("Latest run in period: #{$runA->id} completed");
    expect($output)->toContain('Changes: added=1 removed=2 changed=3 unchanged=4');
    expect($output)->toContain('Active issues: 2 errors=1 warnings=1 info=0');
    expect($output)->toContain('  - [error] test: boom');
    expect($output)->toContain('  - [warning] test: warn');

    expect($output)->toContain('Summary B');
    expect($output)->toContain("Source key: {$sourceB->key}");
    expect($output)->toContain("Latest run in period: #{$runB->id} baseline");
    expect($output)->toContain('Changes: added=0 removed=0 changed=0 unchanged=0');
    expect($output)->toContain('Active issues: 0 errors=0 warnings=0 info=0');
});

it('does not send email without --email', function () {
    Mail::fake();

    $exitCode = Artisan::call('contextual-console:daily-summary');

    expect($exitCode)->toBe(0);
    Mail::assertNothingSent();
});

it('sends email with --email when recipient is configured', function () {
    Mail::fake();
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');

    $source = MonitoredSource::create(['key' => 'hb:mail', 'name' => 'Mail Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 1, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary', ['--email' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Daily monitoring summary');
    expect($output)->toContain("Latest run in period: #{$run->id} completed");
    Mail::assertSent(ContextualConsoleDailySummaryMail::class, function ($mail) {
        $html = view('emails.contextual-console.daily-summary-html', [
            'report' => $mail->report,
        ])->render();

        return $mail->hasTo('ops@example.test')
            && str_contains($mail->summary, 'Daily monitoring summary')
            && str_contains($mail->summary, 'Mail Source')
            && str_contains($html, 'Contextual Console');
    });
});

it('fails clearly with --email when recipient is missing', function () {
    Mail::fake();
    config()->set('contextual_console.daily_summary_to', null);

    $exitCode = Artisan::call('contextual-console:daily-summary', ['--email' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(1);
    expect($output)->toContain('Daily summary email requested, but no recipient is configured.');
    Mail::assertNothingSent();
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
    expect($output2)->toContain('Daily monitoring summary');
    expect($output2)->toContain('Period: last 48 hour(s)');
    expect($output2)->toContain("Source key: {$source->key}");
    expect($output2)->toContain("Latest run in period: #{$run->id} completed");
});

it('includes old and new values for change-driven issues', function () {
    config(['app.schedule_timezone' => 'Europe/London']);

    $source = MonitoredSource::create(['key' => 'hb:sum-change', 'name' => 'Change Summary Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 0, 'removed' => 0, 'changed' => 1, 'unchanged' => 0],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => $snapshot->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED,
        'severity' => 'info',
        'message' => 'Plot status changed.',
        'context' => [
            'field' => 'status',
            'old_value' => 'available',
            'new_value' => 'reserved',
        ],
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => $snapshot->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED,
        'severity' => 'info',
        'message' => 'Plot price changed.',
        'context' => [
            'field' => 'price',
            'old_value' => '100000',
            'new_value' => '110000',
        ],
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('plot_status_changed: Plot status changed. (available -> reserved)');
    expect($output)->toContain('plot_price_changed: Plot price changed. (100000 -> 110000)');
});

it('does not treat a recovered source_run_failed issue as an active problem', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:sum-recovered-fail',
        'name' => 'Recovered Failure Source',
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subHours(2),
        'finished_at' => now()->subHours(2),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $failedRun->id,
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'severity' => 'error',
        'message' => 'SOURCE_RUN_FAILED_RECOVERED',
    ]);

    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour(),
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Active issues: 0 errors=0 warnings=0 info=0');
    expect($output)->not->toContain('SOURCE_RUN_FAILED_RECOVERED');
    expect($output)->not->toContain('(still failing)');
});

it('notes recovery when the latest run in the period failed but a later run succeeded overall', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));

    try {
        $source = MonitoredSource::create([
            'key' => 'hb:sum-stale-period-fail',
            'name' => 'Stale Period Failure Source',
        ]);

        $failedInPeriod = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => Carbon::parse('2026-05-14 10:00:00', 'UTC'),
            'finished_at' => Carbon::parse('2026-05-14 10:05:00', 'UTC'),
        ]);

        DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 1],
            'started_at' => Carbon::parse('2026-05-13 10:00:00', 'UTC'),
            'finished_at' => Carbon::parse('2026-05-13 10:05:00', 'UTC'),
        ]);

        $exitCode = Artisan::call('contextual-console:daily-summary');
        $output = Artisan::output();

        expect($exitCode)->toBe(0);
        expect($output)->toContain("Latest run in period: #{$failedInPeriod->id} failed");
        expect($output)->toContain('has since recovered');
        expect($output)->toContain('Active issues: 0 errors=0 warnings=0 info=0');
    } finally {
        Carbon::setTestNow();
    }
});

it('marks a current source_run_failed issue as still failing', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:sum-current-fail',
        'name' => 'Current Failure Source',
    ]);

    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subDays(2),
        'finished_at' => now()->subDays(2),
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $failedRun->id,
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'severity' => 'error',
        'message' => 'SOURCE_RUN_FAILED_CURRENT',
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary');
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Active issues: 1 errors=1 warnings=0 info=0');
    expect($output)->toContain('source_run_failed: SOURCE_RUN_FAILED_CURRENT (still failing)');
});

it('formats period timestamps in the configured schedule timezone', function () {
    config(['app.schedule_timezone' => 'Europe/London']);
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));

    try {
        $source = MonitoredSource::create(['key' => 'hb:sum-tz', 'name' => 'Timezone Source']);
        $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);

        DatasetComparisonRun::create([
            'source_id' => $source->id,
            'current_snapshot_id' => $snapshot->id,
            'previous_snapshot_id' => null,
            'status' => 'completed',
            'summary' => ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
            'started_at' => Carbon::parse('2026-05-14 11:00:00', 'UTC'),
            'finished_at' => Carbon::parse('2026-05-14 11:05:00', 'UTC'),
        ]);

        $exitCode = Artisan::call('contextual-console:daily-summary');
        $output = Artisan::output();

        expect($exitCode)->toBe(0);
        expect($output)->toContain('Period: last 24 hour(s) (since 2026-05-13 13:00:00)');
        expect($output)->toContain('finished 2026-05-14 12:05:00');
    } finally {
        Carbon::setTestNow();
    }
});
