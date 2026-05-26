<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Carbon\Carbon;
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

it('shows issues from multiple sources with source names', function () {
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
        ->assertSeeText('Issues Multi Source B')
        ->assertDontSeeText('hb:issues-multi-a')
        ->assertDontSeeText('hb:issues-multi-b');
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

it('shows old and new values for plot status and price change issues', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-change-detail',
        'name' => 'Issues Change Detail Source',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];
    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    $statusIssue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED)
        ->firstOrFail();

    $priceIssue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED)
        ->firstOrFail();

    expect($statusIssue->context['old_value'])->toBe('available');
    expect($statusIssue->context['new_value'])->toBe('reserved');
    expect($priceIssue->context['old_value'])->toBe('100000');
    expect($priceIssue->context['new_value'])->toBe('110000');

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Plot status changed.')
        ->assertSeeText('available -> reserved')
        ->assertSeeText('Plot price changed.')
        ->assertSeeText('100000 -> 110000')
        ->assertSee('data-test="issue-change-detail"', false);
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
        ->assertSeeText('View issue #'.(string) $plotIssue->id)
        ->assertSeeText('Plot status is invalid.')
        ->assertSeeText('invalid_value');
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
        'created_at' => Carbon::parse('2026-05-01 12:00:00'),
        'updated_at' => Carbon::parse('2026-05-01 12:00:00'),
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
        'created_at' => Carbon::parse('2026-05-10 12:00:00'),
        'updated_at' => Carbon::parse('2026-05-10 12:00:00'),
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
        'created_at' => Carbon::parse('2026-05-20 12:00:00'),
        'updated_at' => Carbon::parse('2026-05-20 12:00:00'),
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

it('retains selected visible issue filters in the filter form and shows a clear link', function () {
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
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))
        ->assertOk()
        ->assertSee('href="'.route('issues.index').'"', false)
        ->assertSeeText('Clear filters')
        ->getContent();

    expect($html)->toMatch('/<option value="'.$source->id.'"[^>]*\bselected\b/');
    expect($html)->toMatch('/<option value="warning"[^>]*\bselected\b/');
    expect($html)->toContain('name="date_from" value="2026-05-01"');
    expect($html)->toContain('name="date_to" value="2026-05-31"');
    expect($html)->not->toMatch('/<form[^>]*cc-filter-form[^>]*>[\s\S]*name="issue_type"/');
});

it('shows a simplified review-focused issues table and filter form', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-simplified-ui',
        'name' => 'Issues Simplified UI Source',
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
        'issue_type' => 'issues_simplified_type',
        'field' => 'status',
        'severity' => 'warning',
        'message' => 'ISSUES_SIMPLIFIED_UI_MARKER',
    ]);

    $html = $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('ISSUES_SIMPLIFIED_UI_MARKER')
        ->assertSeeText('issues_simplified_type')
        ->assertSeeText('field: status')
        ->getContent();

    expect($html)->toContain('<th>Severity</th>');
    expect($html)->toContain('<th>Source</th>');
    expect($html)->toContain('<th>Run</th>');
    expect($html)->toContain('<th>Entity</th>');
    expect($html)->toContain('<th>Message</th>');
    expect($html)->toContain('<th>Recorded</th>');
    expect($html)->toContain('<th>Review status</th>');
    expect($html)->not->toContain('<th>Status</th>');
    expect($html)->toMatch('/<th>Recorded<\/th>[\s\S]*<th>Review status<\/th>/');
    expect($html)->not->toContain('<th>Issue type</th>');
    expect($html)->not->toContain('<th>Source key</th>');
    expect($html)->not->toContain('<th>Field</th>');
    expect($html)->toContain('cc-filter-form--issues');
    expect($html)->toContain('name="issue_status"');
    expect($html)->not->toMatch('/<form[^>]*cc-filter-form[^>]*>[\s\S]*name="issue_type"/');
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

it('includes active in the review status filter dropdown with correct selection', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::FILTER_ACTIVE]))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<option value="active"[^>]*>Active<\/option>/');
    expect($html)->toMatch('/<option value="active"[^>]*\bselected\b/');
    expect($html)->toMatch('/<option value="open"[^>]*>Open<\/option>/');
    expect($html)->toMatch('/<option value="resolved"[^>]*>Resolved<\/option>/');
});

it('filters issues by active review status via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-active',
        'name' => 'Issues Filter Active Source',
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
        'issue_type' => 'issues_filter_active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_FILTER_ACTIVE_OPEN',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
        'message' => 'ISSUES_FILTER_ACTIVE_ACK',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_IGNORED,
        'message' => 'ISSUES_FILTER_ACTIVE_IGNORED',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_filter_active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'ISSUES_FILTER_ACTIVE_RESOLVED',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::FILTER_ACTIVE]))
        ->assertOk()
        ->assertSeeText('ISSUES_FILTER_ACTIVE_OPEN')
        ->assertSeeText('ISSUES_FILTER_ACTIVE_ACK')
        ->assertDontSeeText('ISSUES_FILTER_ACTIVE_IGNORED')
        ->assertDontSeeText('ISSUES_FILTER_ACTIVE_RESOLVED');
});

it('shows a specific empty state when the active review status filter has no matches', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-empty-active',
        'name' => 'Issues Empty Active Source',
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
        'issue_type' => 'issues_empty_active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'ISSUES_EMPTY_ACTIVE_RESOLVED_ONLY',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::FILTER_ACTIVE]))
        ->assertOk()
        ->assertSeeText('No active issues match the current filters.')
        ->assertDontSeeText('ISSUES_EMPTY_ACTIVE_RESOLVED_ONLY');
});

it('ignores invalid review status filter query values', function () {
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

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_invalid_status',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_INVALID_STATUS_SHOWN',
    ]);

    $html = $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => 'not-a-real-status']))
        ->assertOk()
        ->assertSeeText('ISSUES_INVALID_STATUS_SHOWN')
        ->getContent();

    expect($html)->not->toMatch('/<option value="not-a-real-status"[^>]*\bselected\b/');
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

it('shows the bulk update form only when filters are active', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertDontSee('data-test="issues-bulk-form"', false);

    $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::FILTER_ACTIVE]))
        ->assertOk()
        ->assertSee('data-test="issues-bulk-form"', false)
        ->assertSeeText('Bulk review')
        ->assertSeeText('not only the');
});

it('shows the filtered issue count and visible range on the issues page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-count-summary',
        'name' => 'Issues Count Summary Source',
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

    for ($i = 0; $i < 101; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_count_summary',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_COUNT_SUMMARY_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::STATUS_OPEN]))
        ->assertOk()
        ->assertSee('data-test="issues-result-summary"', false)
        ->assertSeeText('101 issues match the current filters.')
        ->assertSeeText('Showing 1 to 100.')
        ->assertSee('data-test="issues-pagination"', false)
        ->assertSee('aria-current="page">1<', false)
        ->assertSeeText('ISSUES_COUNT_SUMMARY_0')
        ->assertSeeText('ISSUES_COUNT_SUMMARY_99')
        ->assertDontSeeText('ISSUES_COUNT_SUMMARY_100')
        ->assertDontSeeText('Showing newest 100.');
});

it('paginates issues beyond the first page while preserving newest-first order', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-pagination',
        'name' => 'Issues Pagination Source',
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

    for ($i = 0; $i < 101; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_pagination',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_PAGINATION_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    $this->actingAs($user)
        ->get(route('issues.index', [
            'issue_status' => DatasetIssue::STATUS_OPEN,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertSeeText('Showing 101 to 101.')
        ->assertSeeText('ISSUES_PAGINATION_100')
        ->assertDontSeeText('ISSUES_PAGINATION_0')
        ->assertSee('data-test="issues-pagination"', false)
        ->assertSee('aria-current="page">2<', false)
        ->assertSee('cc-pagination__link--active', false);
});

it('shows numbered pagination links when there are multiple pages', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-numbered-pagination',
        'name' => 'Issues Numbered Pagination Source',
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

    for ($i = 0; $i < 101; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_numbered_pagination',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_NUMBERED_PAGINATION_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    $html = $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::STATUS_OPEN]))
        ->assertOk()
        ->assertSee('data-test="issues-pagination"', false)
        ->assertSeeText('First')
        ->assertSeeText('Last')
        ->getContent();

    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=2[^"]*">2<\/a>/');
    expect($html)->toMatch('/aria-current="page">1<\//');
});

it('uses compact windowed numbered pagination when there are many pages', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-windowed-pagination',
        'name' => 'Issues Windowed Pagination Source',
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

    for ($i = 0; $i < 1001; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_windowed_pagination',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_WINDOWED_PAGINATION_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    $html = $this->actingAs($user)
        ->get(route('issues.index', [
            'issue_status' => DatasetIssue::STATUS_OPEN,
            'page' => 6,
        ]))
        ->assertOk()
        ->assertSee('data-test="issues-pagination"', false)
        ->assertSee('aria-current="page">6<', false)
        ->getContent();

    expect($html)->toContain('cc-pagination__ellipsis');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=1[^"]*">1<\/a>/');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=4[^"]*">4<\/a>/');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=8[^"]*">8<\/a>/');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=11[^"]*">11<\/a>/');
    expect($html)->not->toMatch('/<a class="cc-pagination__link" href="[^"]*page=3[^"]*">3<\/a>/');
    expect($html)->not->toMatch('/<a class="cc-pagination__link" href="[^"]*page=9[^"]*">9<\/a>/');
    expect(substr_count($html, 'class="cc-pagination__ellipsis muted"'))->toBe(2);
    expect(preg_match_all('/class="cc-pagination__link" href="[^"]*page=\d+[^"]*">\d+<\/a>/', $html))->toBe(6);
});

it('preserves issue filters in pagination links', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-pagination-filters',
        'name' => 'Issues Pagination Filters Source',
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

    for ($i = 0; $i < 101; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_pagination_filters',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_PAGINATION_FILTERS_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    $html = $this->actingAs($user)
        ->get(route('issues.index', ['issue_status' => DatasetIssue::STATUS_OPEN]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('issue_status=open');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*issue_status=open[^"]*page=2[^"]*">2<\/a>/');
});

it('applies the active review status filter across paginated results', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-pagination-active',
        'name' => 'Issues Pagination Active Source',
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

    for ($i = 0; $i < 101; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_pagination_active',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_PAGINATION_ACTIVE_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_pagination_active',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'ISSUES_PAGINATION_ACTIVE_RESOLVED',
    ]);

    $this->actingAs($user)
        ->get(route('issues.index', [
            'issue_status' => DatasetIssue::FILTER_ACTIVE,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertSeeText('ISSUES_PAGINATION_ACTIVE_100')
        ->assertDontSeeText('ISSUES_PAGINATION_ACTIVE_RESOLVED');
});

it('bulk update still updates all filtered issues beyond the current page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-bulk-pagination',
        'name' => 'Issues Bulk Pagination Source',
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

    for ($i = 0; $i < 101; $i++) {
        $issue = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'issues_bulk_pagination',
            'severity' => 'info',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'ISSUES_BULK_PAGINATION_'.$i,
        ]);
        $issue->forceFill([
            'created_at' => now()->subMinutes($i),
            'updated_at' => now()->subMinutes($i),
        ])->save();
    }

    $this->actingAs($user)
        ->post(route('issues.bulk-update-status'), [
            'status' => DatasetIssue::STATUS_IGNORED,
            'issue_status' => DatasetIssue::STATUS_OPEN,
        ])
        ->assertRedirect(route('issues.index', ['issue_status' => DatasetIssue::STATUS_OPEN]))
        ->assertSessionHas('status', 'Updated 101 issues matching the current filters.');

    expect(DatasetIssue::query()->where('status', DatasetIssue::STATUS_OPEN)->count())->toBe(0);
    expect(DatasetIssue::query()->where('status', DatasetIssue::STATUS_IGNORED)->count())->toBe(101);
});

it('requires an explicit bulk target status', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('issues.bulk-update-status'), [
            'issue_status' => DatasetIssue::STATUS_OPEN,
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($user)
        ->post(route('issues.bulk-update-status'), [
            'status' => '',
            'issue_status' => DatasetIssue::STATUS_OPEN,
        ])
        ->assertSessionHasErrors('status');
});

it('allows authenticated users to bulk update filtered issues', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-bulk-update',
        'name' => 'Issues Bulk Update Source',
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

    $openOne = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_bulk',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_BULK_OPEN_ONE',
    ]);
    $openTwo = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_bulk',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_BULK_OPEN_TWO',
    ]);
    $resolved = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'issues_bulk',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'ISSUES_BULK_RESOLVED',
    ]);

    $this->actingAs($user)
        ->post(route('issues.bulk-update-status'), [
            'status' => DatasetIssue::STATUS_IGNORED,
            'issue_status' => DatasetIssue::STATUS_OPEN,
        ])
        ->assertRedirect(route('issues.index', ['issue_status' => DatasetIssue::STATUS_OPEN]))
        ->assertSessionHas('status', 'Updated 2 issues matching the current filters.');

    expect($openOne->fresh()->status)->toBe(DatasetIssue::STATUS_IGNORED);
    expect($openTwo->fresh()->status)->toBe(DatasetIssue::STATUS_IGNORED);
    expect($resolved->fresh()->status)->toBe(DatasetIssue::STATUS_RESOLVED);
});

it('rejects bulk update when no filters are active', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-bulk-no-filter',
        'name' => 'Issues Bulk No Filter Source',
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
        'issue_type' => 'issues_bulk_no_filter',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_BULK_NO_FILTER',
    ]);

    $this->actingAs($user)
        ->post(route('issues.bulk-update-status'), [
            'status' => DatasetIssue::STATUS_IGNORED,
        ])
        ->assertRedirect(route('issues.index'))
        ->assertSessionHas('status', 'Apply at least one filter before updating issues in bulk.');

    expect($issue->fresh()->status)->toBe(DatasetIssue::STATUS_OPEN);
});

it('rejects invalid bulk target statuses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('issues.bulk-update-status'), [
            'status' => 'not-a-status',
            'issue_status' => DatasetIssue::STATUS_OPEN,
        ])
        ->assertSessionHasErrors('status');
});

it('redirects unauthenticated users from bulk issue status updates to login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:issues-bulk-auth',
        'name' => 'Issues Bulk Auth Source',
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
        'issue_type' => 'issues_bulk_auth',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'ISSUES_BULK_AUTH',
    ]);

    $this->post(route('issues.bulk-update-status'), [
        'status' => DatasetIssue::STATUS_IGNORED,
        'issue_status' => DatasetIssue::STATUS_OPEN,
    ])->assertRedirect(route('login'));
});

it('redirects unauthenticated users from filtered /issues to /login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:issues-filter-auth',
        'name' => 'Issues Auth Source',
    ]);

    $this->get(route('issues.index', ['source' => $source->id]))
        ->assertRedirect(route('login'));
});

it('formats issues index timestamps in the schedule timezone', function () {
    config(['app.schedule_timezone' => 'Europe/London']);

    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-display-time',
        'name' => 'Issues Display Time Source',
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
        'issue_type' => 'issues_display_time',
        'severity' => 'info',
        'message' => 'ISSUES_DISPLAY_TIME_MARKER',
    ]);
    $issue->forceFill([
        'created_at' => $recordedAt,
        'updated_at' => $recordedAt,
    ])->save();

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSee('2026-05-14 06:42:00', false)
        ->assertDontSee('2026-05-14 05:42:00', false);
});
