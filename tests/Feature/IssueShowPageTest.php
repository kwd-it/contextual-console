<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\SourceRunFailedIssueService;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from issue show to login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:issue-show-auth',
        'name' => 'Issue Show Auth Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issue_show_auth',
        'severity' => 'info',
        'message' => 'ISSUE_SHOW_AUTH_MARKER',
    ]);

    $this->get(route('issues.show', $issue))
        ->assertRedirect(route('login'));
});

it('shows operator-friendly issue detail for a plot status change', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issue-show-status-change',
        'name' => 'Issue Show Status Change Source',
    ]);

    $baseline = [
        ['id' => 14, 'price' => 100_000, 'status' => 'available', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
    ];
    $second = [
        ['id' => 14, 'price' => 100_000, 'status' => 'reserved', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    $issue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED)
        ->firstOrFail();

    $developmentHref = route('sources.developments.show', [
        $source,
        \App\Support\DevelopmentRouteSlug::encode('Charminster Farm'),
    ]);
    $runHref = route('sources.runs.show', [$source, $run2]);
    $showHref = route('issues.show', $issue);

    $this->actingAs($user)
        ->get($showHref)
        ->assertOk()
        ->assertSeeText('Issue #'.$issue->id)
        ->assertSeeText('Plot status changed.')
        ->assertSeeText('The plot status changed compared to the previous daily snapshot.')
        ->assertSeeText('Check whether this status movement matches expected sales activity.')
        ->assertSeeText('available -> reserved')
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('Charminster Farm')
        ->assertSeeText('Technical ID: plot:14')
        ->assertSee('data-test="issue-type-explanation"', false)
        ->assertSee('data-test="issue-suggested-check"', false)
        ->assertSee('data-test="issue-change-detail"', false)
        ->assertSee('href="'.$runHref.'"', false)
        ->assertSee('href="'.$developmentHref.'"', false)
        ->assertSee('href="'.route('sources.show', $source).'"', false)
        ->assertSee('data-test="issue-show-status-form"', false);
});

it('shows source run failure context on the issue detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issue-show-run-failed',
        'name' => 'Issue Show Run Failed Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'severity' => 'error',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'Scheduled source run failed for hb:issue-show-run-failed.',
        'context' => [
            'exception_message' => 'ISSUE_SHOW_EXCEPTION_MESSAGE',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('issues.show', $issue))
        ->assertOk()
        ->assertSeeText('The scheduled or manual source run did not complete successfully.')
        ->assertSeeText('Check the failed run details first. If the source later recovered, this may already be resolved.')
        ->assertSee('data-test="issue-suggested-check"', false)
        ->assertSeeText('exception_message')
        ->assertSeeText('ISSUE_SHOW_EXCEPTION_MESSAGE')
        ->assertSee('data-test="issue-context-table"', false);
});

it('links issue messages from the issues index to the detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issue-show-index-link',
        'name' => 'Issue Show Index Link Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issue_show_index_link',
        'severity' => 'warning',
        'message' => 'ISSUE_SHOW_INDEX_LINK_MESSAGE',
    ]);

    $showHref = route('issues.show', $issue);

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSee('href="'.$showHref.'"', false)
        ->assertSee('data-test="issue-detail-link"', false)
        ->assertSeeText('View issue #'.$issue->id);
});

it('returns to the issue detail page after a status update from show', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issue-show-status-update',
        'name' => 'Issue Show Status Update Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issue_show_status_update',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUE_SHOW_STATUS_UPDATE_MARKER',
    ]);

    $this->actingAs($user)
        ->patch(route('issues.update-status', $issue), [
            'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
            'return_to' => 'show',
        ])
        ->assertRedirect(route('issues.show', $issue))
        ->assertSessionHas('status', 'Issue status updated.');

    expect($issue->fresh()->status)->toBe(DatasetIssue::STATUS_ACKNOWLEDGED);
});

it('formats issue detail timestamps in the schedule timezone', function () {
    config(['app.schedule_timezone' => 'Europe/London']);

    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issue-show-display-time',
        'name' => 'Issue Show Display Time Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $recordedAt = Carbon::parse('2026-05-14 05:42:00', 'UTC');
    $issue = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issue_show_display_time',
        'severity' => 'info',
        'message' => 'ISSUE_SHOW_DISPLAY_TIME_MARKER',
    ]);
    $issue->forceFill([
        'created_at' => $recordedAt,
        'updated_at' => $recordedAt,
    ])->save();

    $this->actingAs($user)
        ->get(route('issues.show', $issue))
        ->assertOk()
        ->assertSee('2026-05-14 06:42:00', false)
        ->assertDontSee('2026-05-14 05:42:00', false);
});
