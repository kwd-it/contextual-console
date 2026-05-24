<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from /issues to /login', function () {
    $this->get('/issues')
        ->assertRedirect(route('login'));
});

it('allows authenticated users to load /issues', function () {
    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Issues')
        ->assertSeeText('Recent issues from daily dataset comparisons across all sources');
});

it('links to the issues page from the sources index navigation', function () {
    $href = route('issues.index');

    $this->actingAs(User::factory()->create())
        ->get('/sources')
        ->assertOk()
        ->assertSee('href="'.$href.'"', false);
});

it('shows issues from multiple sources with source names and keys', function () {
    $user = User::factory()->create();

    $sourceA = MonitoredSource::create([
        'key' => 'hb:issues-multi-a',
        'name' => 'Issues Multi Source A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:issues-multi-b',
        'name' => 'Issues Multi Source B',
    ]);

    $badA = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record-a',
    ];
    $badB = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record-b',
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($sourceA, $badA);
    $service->run($sourceB, $badB);

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Issues Multi Source A')
        ->assertSeeText('hb:issues-multi-a')
        ->assertSeeText('Issues Multi Source B')
        ->assertSeeText('hb:issues-multi-b');
});

it('lists newest issues first by recorded time', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-order',
        'name' => 'Issues Order Source',
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

    $older = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'older_marker',
        'severity' => 'info',
        'message' => 'ISSUES_PAGE_ORDER_OLDER',
    ]);
    $older->forceFill([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ])->save();

    $newer = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'newer_marker',
        'severity' => 'info',
        'message' => 'ISSUES_PAGE_ORDER_NEWER',
    ]);
    $newer->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->save();

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeInOrder([
            'ISSUES_PAGE_ORDER_NEWER',
            'ISSUES_PAGE_ORDER_OLDER',
        ]);
});

it('links run ids to the existing comparison run detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-run-link',
        'name' => 'Issues Run Link Source',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];
    $bad = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record',
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $bad);
    $run2->refresh();

    $href = route('sources.runs.show', [$source, $run2]);

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSee('href="'.$href.'"', false);
});

it('shows snapshot-derived plot labels and keeps technical ids visible', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-plot-labels',
        'name' => 'Issues Plot Labels Source',
    ]);

    $baseline = [
        ['id' => 14, 'price' => 100_000, 'status' => 'available', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
    ];
    $second = [
        ['id' => 14, 'price' => 110_000, 'status' => 'not_a_status', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    expect($run2->status)->toBe('completed');

    $plotIssue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('entity_type', 'plot')
        ->first();
    expect($plotIssue)->not->toBeNull();

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('Charminster Farm')
        ->assertSeeText('Technical ID: plot:14')
        ->assertSeeText('Issue id: '.(string) $plotIssue->id)
        ->assertSeeText('Plot status is invalid.');
});

it('filters issues by source via GET query', function () {
    $user = User::factory()->create();

    $sourceA = MonitoredSource::create([
        'key' => 'hb:issues-filter-src-a',
        'name' => 'Issues Filter Source A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:issues-filter-src-b',
        'name' => 'Issues Filter Source B',
    ]);

    $runA = DatasetComparisonRun::create([
        'source_id' => $sourceA->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);
    $runB = DatasetComparisonRun::create([
        'source_id' => $sourceB->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $sourceA->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $runA->id,
        'issue_type' => 'issues_filter_src',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_SRC_A_MARKER',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $sourceB->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $runB->id,
        'issue_type' => 'issues_filter_src',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_SRC_B_MARKER',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', ['source' => $sourceA->id]))
        ->assertOk()
        ->assertSeeText('ISSUES_FILTER_SRC_A_MARKER')
        ->assertDontSeeText('ISSUES_FILTER_SRC_B_MARKER');
});

it('filters issues by severity via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-sev',
        'name' => 'Issues Filter Sev Source',
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

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_sev',
        'severity' => 'error',
        'message' => 'ISSUES_FILTER_SEV_ERROR',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_sev',
        'severity' => 'warning',
        'message' => 'ISSUES_FILTER_SEV_WARNING',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', ['severity' => 'error']))
        ->assertOk()
        ->assertSeeText('ISSUES_FILTER_SEV_ERROR')
        ->assertDontSeeText('ISSUES_FILTER_SEV_WARNING');
});

it('filters issues by issue type via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-type',
        'name' => 'Issues Filter Type Source',
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

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_type_alpha',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_TYPE_ALPHA',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_type_beta',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_TYPE_BETA',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', ['issue_type' => 'issues_filter_type_alpha']))
        ->assertOk()
        ->assertSeeText('ISSUES_FILTER_TYPE_ALPHA')
        ->assertDontSeeText('ISSUES_FILTER_TYPE_BETA');
});

it('filters issues by created_at date range via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-dates',
        'name' => 'Issues Filter Dates Source',
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

    $early = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_dates',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_DATE_EARLY',
    ]);
    $early->forceFill([
        'created_at' => \Carbon\Carbon::parse('2026-05-01 12:00:00'),
        'updated_at' => \Carbon\Carbon::parse('2026-05-01 12:00:00'),
    ])->save();

    $mid = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_dates',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_DATE_MID',
    ]);
    $mid->forceFill([
        'created_at' => \Carbon\Carbon::parse('2026-05-10 12:00:00'),
        'updated_at' => \Carbon\Carbon::parse('2026-05-10 12:00:00'),
    ])->save();

    $late = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_dates',
        'severity' => 'info',
        'message' => 'ISSUES_FILTER_DATE_LATE',
    ]);
    $late->forceFill([
        'created_at' => \Carbon\Carbon::parse('2026-05-20 12:00:00'),
        'updated_at' => \Carbon\Carbon::parse('2026-05-20 12:00:00'),
    ])->save();

    $this->actingAs($user)
        ->get(route('issues.index', [
            'date_from' => '2026-05-05',
            'date_to' => '2026-05-15',
        ]))
        ->assertOk()
        ->assertDontSeeText('ISSUES_FILTER_DATE_EARLY')
        ->assertSeeText('ISSUES_FILTER_DATE_MID')
        ->assertDontSeeText('ISSUES_FILTER_DATE_LATE');
});

it('retains selected issue filters in the filter form and shows a clear link', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-retain',
        'name' => 'Issues Retain Source',
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

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_retain_type',
        'severity' => 'warning',
        'message' => 'ISSUES_RETAIN_BODY',
    ]);

    $html = $this->actingAs($user)
        ->get(route('issues.index', [
            'source' => $source->id,
            'severity' => 'warning',
            'issue_type' => 'issues_retain_type',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))
        ->assertOk()
        ->assertSee('href="'.route('issues.index').'"', false)
        ->assertSeeText('Clear filters')
        ->getContent();

    expect($html)->toMatch('/<option value="'.$source->id.'"[^>]*\bselected\b/');
    expect($html)->toMatch('/<option value="warning"[^>]*\bselected\b/');
    expect($html)->toMatch('/<option value="issues_retain_type"[^>]*\bselected\b/');
    expect($html)->toContain('name="date_from" value="2026-05-01"');
    expect($html)->toContain('name="date_to" value="2026-05-31"');
});

it('shows each issue review status on the issues page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-show-status',
        'name' => 'Issues Show Status Source',
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

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_show_status',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
        'message' => 'ISSUES_SHOW_STATUS_MARKER',
    ]);

    $html = $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('ISSUES_SHOW_STATUS_MARKER')
        ->getContent();

    expect($html)->toMatch('/<option value="acknowledged"[^>]*\bselected\b/');
});

it('filters issues by review status via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-status',
        'name' => 'Issues Filter Status Source',
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

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_status',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_FILTER_STATUS_OPEN',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_status',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'ISSUES_FILTER_STATUS_RESOLVED',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::STATUS_OPEN]))
        ->assertOk()
        ->assertSeeText('ISSUES_FILTER_STATUS_OPEN')
        ->assertDontSeeText('ISSUES_FILTER_STATUS_RESOLVED');
});

it('allows authenticated users to update issue review status', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-update-status',
        'name' => 'Issues Update Status Source',
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
        'issue_type' => 'issues_update_status',
        'severity' => 'warning',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_UPDATE_STATUS_MARKER',
    ]);

    $this->actingAs($user)
        ->patch(route('issues.update-status', $issue), [
            'status' => DatasetIssue::STATUS_IGNORED,
        ])
        ->assertRedirect(route('issues.index'))
        ->assertSessionHas('status', 'Issue status updated.');

    expect($issue->fresh()->status)->toBe(DatasetIssue::STATUS_IGNORED);
});

it('rejects invalid issue review status updates', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-invalid-status',
        'name' => 'Issues Invalid Status Source',
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
        'issue_type' => 'issues_invalid_status',
        'severity' => 'info',
        'message' => 'ISSUES_INVALID_STATUS_MARKER',
    ]);

    $this->actingAs($user)
        ->patch(route('issues.update-status', $issue), [
            'status' => 'not-a-real-status',
        ])
        ->assertSessionHasErrors('status');

    expect($issue->fresh()->status)->toBe(DatasetIssue::STATUS_OPEN);
});

it('redirects unauthenticated users from issue status updates to login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:issues-status-auth',
        'name' => 'Issues Status Auth Source',
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
        'issue_type' => 'issues_status_auth',
        'severity' => 'info',
        'message' => 'ISSUES_STATUS_AUTH_MARKER',
    ]);

    $this->patch(route('issues.update-status', $issue), [
        'status' => DatasetIssue::STATUS_RESOLVED,
    ])->assertRedirect(route('login'));
});

it('retains review status filter in the filter form and preserves filters after status update', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-status-retain',
        'name' => 'Issues Status Retain Source',
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
        'issue_type' => 'issues_status_retain',
        'severity' => 'warning',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_STATUS_RETAIN_MARKER',
    ]);

    $html = $this->actingAs($user)
        ->get(route('issues.index', [
            'source' => $source->id,
            'issue_status' => DatasetIssue::STATUS_OPEN,
            'severity' => 'warning',
            'issue_type' => 'issues_status_retain',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<option value="open"[^>]*\bselected\b/');
    expect($html)->toContain('name="issue_status"');
    expect($html)->toContain('name="date_from" value="2026-05-01"');

    $this->actingAs($user)
        ->patch(route('issues.update-status', $issue), [
            'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
            'source' => $source->id,
            'issue_status' => DatasetIssue::STATUS_OPEN,
            'severity' => 'warning',
            'issue_type' => 'issues_status_retain',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ])
        ->assertRedirect(route('issues.index', [
            'source' => $source->id,
            'issue_status' => DatasetIssue::STATUS_OPEN,
            'severity' => 'warning',
            'issue_type' => 'issues_status_retain',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]));
});

it('redirects unauthenticated users from filtered /issues to /login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-auth',
        'name' => 'Issues Auth Source',
    ]);

    $this->get(route('issues.index', ['source' => $source->id]))
        ->assertRedirect(route('login'));
});
