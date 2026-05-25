<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Mail\ContextualConsoleDailySummaryMail;
use App\Support\DailyMonitoringSummaryBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dailySummaryMailFromBuilder(): ContextualConsoleDailySummaryMail
{
    $report = app(DailyMonitoringSummaryBuilder::class)->buildReport(24);

    return new ContextualConsoleDailySummaryMail($report);
}

function dailySummaryHtml(ContextualConsoleDailySummaryMail $mail): string
{
    return view('emails.contextual-console.daily-summary-html', [
        'report' => $mail->report,
    ])->render();
}

it('renders html and plain text parts from the structured report', function () {
    $source = MonitoredSource::create(['key' => 'hb:mail-html', 'name' => 'HTML Mail Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 1, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    $mail = dailySummaryMailFromBuilder();
    $html = dailySummaryHtml($mail);

    $mail->assertHasSubject('Daily monitoring summary');
    $mail->assertSeeInText('Daily monitoring summary');
    $mail->assertSeeInText('HTML Mail Source');

    expect($html)->toContain('Contextual Console');
    expect($html)->toContain('Daily monitoring summary');
    expect($html)->toContain('HTML Mail Source');
    expect($html)->toContain('Latest run in period');
    expect($html)->toContain('>Added</th>');
    expect($html)->toContain('>Removed</th>');
    expect($html)->toContain('>Changed</th>');
    expect($html)->toContain('>Unchanged</th>');
    expect($html)->toContain('>Errors</th>');
    expect($html)->toContain('>Warnings</th>');
    expect($html)->toContain('>Info</th>');
    expect($html)->toContain('>Total active issues</th>');
    expect($mail->summary)->toContain('Daily monitoring summary');
    expect($mail->summary)->toContain('Changes: added=');
});

it('includes status and price change details in html and text', function () {
    $source = MonitoredSource::create(['key' => 'hb:mail-change', 'name' => 'Change Mail Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 0, 'removed' => 0, 'changed' => 2, 'unchanged' => 0],
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

    $mail = dailySummaryMailFromBuilder();
    $html = dailySummaryHtml($mail);

    $mail->assertSeeInText('plot_status_changed: Plot status changed. (available -> reserved)');
    $mail->assertSeeInText('plot_price_changed: Plot price changed. (100000 -> 110000)');
    expect($html)->toContain('available -&gt; reserved');
    expect($html)->toContain('100000 -&gt; 110000');
    expect($html)->toContain('plot_status_changed');
    expect($html)->toContain('plot_price_changed');
    expect($html)->toContain('Issue details');
});

it('renders changes and active issue counts as labeled html grids', function () {
    $source = MonitoredSource::create(['key' => 'hb:mail-grids', 'name' => 'Grid Mail Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 2, 'removed' => 1, 'changed' => 3, 'unchanged' => 4],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    $html = dailySummaryHtml(dailySummaryMailFromBuilder());

    expect($html)->toContain('>Added</th>');
    expect($html)->toContain('>Removed</th>');
    expect($html)->toContain('>Changed</th>');
    expect($html)->toContain('>Unchanged</th>');
    expect($html)->toContain('>Errors</th>');
    expect($html)->toContain('>Warnings</th>');
    expect($html)->toContain('>Info</th>');
    expect($html)->toContain('>Total active issues</th>');
    expect($html)->not->toContain('added=2 removed=1');
});

it('represents recovered failures clearly in html and text', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));

    try {
        $source = MonitoredSource::create([
            'key' => 'hb:mail-recovered',
            'name' => 'Recovered Mail Source',
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

        $mail = dailySummaryMailFromBuilder();
        $html = dailySummaryHtml($mail);

        $mail->assertSeeInText("Latest run in period: #{$failedInPeriod->id} failed");
        $mail->assertSeeInText('has since recovered');
        expect($html)->toContain('has since recovered');
        expect($html)->toContain('failed');
    } finally {
        Carbon::setTestNow();
    }
});

it('marks a current source_run_failed issue as still failing in html and text', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:mail-current-fail',
        'name' => 'Current Fail Mail Source',
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

    $mail = dailySummaryMailFromBuilder();
    $html = dailySummaryHtml($mail);

    $mail->assertSeeInText('source_run_failed: SOURCE_RUN_FAILED_CURRENT (still failing)');
    expect($html)->toContain('(still failing)');
    expect($html)->toContain('SOURCE_RUN_FAILED_CURRENT');
    expect($html)->toContain('source_run_failed');
    expect($html)->toContain('Issue details');
});

it('does not introduce mojibake or smart punctuation in rendered mail', function () {
    $source = MonitoredSource::create(['key' => 'hb:mail-ascii', 'name' => 'ASCII Mail Source']);
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
            'old_value' => 'a',
            'new_value' => 'b',
        ],
    ]);

    $mail = dailySummaryMailFromBuilder();
    $html = dailySummaryHtml($mail);

    $badPattern = '/ÔÇö|ÔåÆ|├ö|┬À|—|→|“|”|‘|’/u';

    expect($html)->not->toMatch($badPattern);
    expect($mail->summary)->not->toMatch($badPattern);
    $mail->assertSeeInText('(a -> b)');
    expect($html)->toContain('a -&gt; b');
});

it('includes a distinct html body via the mailable content definition', function () {
    $source = MonitoredSource::create(['key' => 'hb:mail-parts', 'name' => 'Parts Mail Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    $mail = dailySummaryMailFromBuilder();
    $content = $mail->content();

    expect($content->html)->toBe('emails.contextual-console.daily-summary-html');
    expect($content->text)->toBeNull();
    expect(dailySummaryHtml($mail))->toContain('<!DOCTYPE html>');
});

it('renders the generated summary verbatim as the plain text body', function () {
    $source = MonitoredSource::create(['key' => 'hb:mail-plain', 'name' => 'Plain Body Source']);
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

    $mail = dailySummaryMailFromBuilder();

    $mail->assertSeeInText('Daily monitoring summary');
    $mail->assertSeeInText('Plain Body Source');
    $mail->assertSeeInText('plot_status_changed: Plot status changed. (available -> reserved)');
    $mail->assertDontSeeInText('&gt;');
    $mail->assertDontSeeInText('-&gt;');
});
