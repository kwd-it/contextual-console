<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from /dashboard to /login', function () {
    $this->get('/dashboard')
        ->assertRedirect(route('login'));
});

it('allows authenticated users to load /dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Dashboard')
        ->assertSeeText('Summary of monitored website datasets')
        ->assertSeeText('View');
});

it('links dashboard summary drilldowns to issues, changes, and sources using the same 7-day date anchor as counts', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $expectedDateFrom = Carbon::now()->subDays(7)->toDateString();
        $user = User::factory()->create();
        $source = MonitoredSource::create([
            'key' => 'hb:dash-drill-links',
            'name' => 'Dashboard Drill Links Source',
        ]);
        $run = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_drill_warning',
            'severity' => 'warning',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'DASHBOARD_DRILL_WARNING',
        ]);
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_drill_error',
            'severity' => 'error',
            'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
            'message' => 'DASHBOARD_DRILL_ERROR',
        ]);

        $html = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $href = static fn (string $dataTest): string => (string) $xpath->evaluate("string(//a[@data-test='{$dataTest}']/@href)");

        expect($href('dashboard-drill-active-issues'))->toBe(route('issues.index', [
            'issue_status' => DatasetIssue::FILTER_ACTIVE,
        ]));
        expect($href('dashboard-drill-active-warnings'))->toBe(route('issues.index', [
            'issue_status' => DatasetIssue::FILTER_ACTIVE,
            'severity' => 'warning',
        ]));
        expect($href('dashboard-drill-active-errors'))->toBe(route('issues.index', [
            'issue_status' => DatasetIssue::FILTER_ACTIVE,
            'severity' => 'error',
        ]));
        expect($href('dashboard-drill-active-info'))->toBe('');
        expect($href('dashboard-drill-changes-7d'))->toBe(route('changes.index', ['date_from' => $expectedDateFrom]));
        expect($href('dashboard-drill-sources-total'))->toBe(route('sources.index'));
    } finally {
        Carbon::setTestNow();
    }
});

it('shows the dashboard link in navigation with the correct href', function () {
    $user = User::factory()->create();
    $dashboardHref = route('dashboard.index');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('href="'.$dashboardHref.'"', false)
        ->assertSeeText('Dashboard');
});

it('shows helpful empty states when there is little or no data', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Development overview')
        ->assertSeeText('No snapshot development data')
        ->assertSeeText('No runs yet')
        ->assertSeeText('No changes recorded')
        ->assertSeeText('No active issues')
        ->assertSeeText('Comparison runs will appear here after snapshots are ingested and compared.')
        ->assertSee('data-test="dashboard-development-overview-empty"', false)
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-total-sources">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-failed-runs-current">\s*0\s*</');
    expect($html)->toContain('data-test="dashboard-active-issues-none"');
    expect($html)->not->toContain('data-test="dashboard-drill-active-issues"');
    expect($html)->toMatch('/data-test="dashboard-active-issues">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-info">\s*0 info\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-warnings">\s*0 warnings\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-errors">\s*0 errors\s*</');
    expect($html)->toMatch('/data-test="dashboard-changes-7d">\s*0\s*</');
});

it('renders summary counts and recent activity with links', function () {
    $user = User::factory()->create();

    $idle = MonitoredSource::create([
        'key' => 'hb:dash-idle',
        'name' => 'Dashboard Idle Source',
    ]);
    $active = MonitoredSource::create([
        'key' => 'hb:dash-active',
        'name' => 'Dashboard Active Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($active, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $active->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinutes(3),
        'finished_at' => now()->subMinutes(2),
    ]);

    $latestRun = $service->run($active, [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ]);
    $latestRun->refresh();

    $failedHref = route('sources.runs.show', [$active, $failedRun]);
    $latestRunHref = route('sources.runs.show', [$active, $latestRun]);

    $runForIssues = DatasetComparisonRun::query()
        ->where('source_id', $active->id)
        ->where('id', $latestRun->id)
        ->firstOrFail();

    DatasetIssue::create([
        'monitored_source_id' => $active->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $runForIssues->id,
        'issue_type' => 'dashboard_warning',
        'severity' => 'warning',
        'message' => 'DASHBOARD_RECENT_ISSUE_WARNING',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $active->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $runForIssues->id,
        'issue_type' => 'dashboard_error',
        'severity' => 'error',
        'message' => 'DASHBOARD_RECENT_ISSUE_ERROR',
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Recent activity')
        ->assertSeeText('Recent changes')
        ->assertSeeText('Recent active issues')
        ->assertSee('href="'.$latestRunHref.'"', false)
        ->assertSee('href="'.$failedHref.'"', false)
        ->assertSeeText('DASHBOARD_RECENT_ISSUE_WARNING')
        ->assertSeeText('DASHBOARD_RECENT_ISSUE_ERROR')
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-total-sources">\s*2\s*</');
    expect($html)->toMatch('/data-test="dashboard-failed-runs-current">\s*0\s*</');
    expect($html)->toContain('data-test="dashboard-failed-runs-recovered">1</');
    expect($html)->toMatch('/data-test="dashboard-active-issues">\s*4\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-warnings">\s*1 warnings\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-errors">\s*1 errors\s*</');
    expect($html)->toContain('data-test="dashboard-drill-active-issues"');
    expect($html)->toMatch('/data-test="dashboard-changes-7d">\s*[1-9]\d*\s*</');

    $issueRunHref = route('sources.runs.show', [$active, $runForIssues]);
    expect($html)->toContain('href="'.$issueRunHref.'"');
});

it('lists recent issues with links to the source and run when available', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-issue-links',
        'name' => 'Dashboard Issue Links Source',
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
        'issue_type' => 'dashboard_link',
        'severity' => 'info',
        'message' => 'DASHBOARD_ISSUE_LINK_MESSAGE',
    ]);

    $sourceHref = route('sources.show', $source);
    $runHref = route('sources.runs.show', [$source, $run]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('DASHBOARD_ISSUE_LINK_MESSAGE')
        ->assertSee('href="'.$sourceHref.'"', false)
        ->assertSee('href="'.$runHref.'"', false);
});

it('links view all changes to the changes index page', function () {
    $changesHref = route('changes.index');

    $html = $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('View all changes')
        ->getContent();

    expect($html)->toContain('href="'.$changesHref.'"');
    expect($html)->toContain('data-test="dashboard-view-all-changes"');
});

it('links to the issues index page from recent active issues', function () {
    $issuesHref = route('issues.index');

    $html = $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Recent active issues')
        ->getContent();

    expect($html)->toContain('href="'.$issuesHref.'"');
    expect($html)->toContain('data-test="dashboard-view-all-issues"');
});

it('shows at most five rows in recent activity, changes, and issues tables', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-limits',
        'name' => 'Dashboard Limits Source',
    ]);

    $runs = [];
    for ($i = 0; $i < 6; $i++) {
        $runs[] = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subMinutes(10 - $i),
            'finished_at' => now()->subMinutes(9 - $i),
        ]);
    }

    for ($i = 0; $i < 6; $i++) {
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_snapshot_id' => null,
            'dataset_comparison_run_id' => $runs[$i]->id,
            'issue_type' => 'dashboard_limit',
            'severity' => 'info',
            'message' => 'DASHBOARD_LIMIT_ISSUE_'.$i,
            'created_at' => now()->subMinutes($i),
        ]);
    }

    for ($i = 0; $i < 6; $i++) {
        ChangeLog::create([
            'dataset_comparison_run_id' => $runs[$i]->id,
            'entity_type' => 'plot',
            'entity_id' => (string) (100 + $i),
            'field' => 'status',
            'old_value' => 'available',
            'new_value' => 'reserved',
            'changed_at' => now()->subMinutes($i),
        ]);
    }

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $recentRunsSection = $xpath->query("//*[@id='hdr-recent-runs']/ancestor::section[1]//tbody/tr");
    expect($recentRunsSection)->not->toBeFalse();
    expect($recentRunsSection->length)->toBe(5);

    $recentChangesSection = $xpath->query("//*[@id='hdr-recent-changes']/ancestor::section[1]//tbody/tr");
    expect($recentChangesSection)->not->toBeFalse();
    expect($recentChangesSection->length)->toBe(5);

    $recentIssuesSection = $xpath->query("//*[@id='hdr-recent-issues']/ancestor::section[1]//tbody/tr");
    expect($recentIssuesSection)->not->toBeFalse();
    expect($recentIssuesSection->length)->toBe(5);
});

it('lists recent plot changes with labels, values, and run links', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-recent-changes',
        'name' => 'Dashboard Recent Changes Source',
    ]);

    $baseline = [
        ['id' => 14, 'price' => 100_000, 'status' => 'available', 'title' => 'Plot 14, The Spetisbury'],
    ];
    $second = [
        ['id' => 14, 'price' => 110_000, 'status' => 'reserved', 'title' => 'Plot 14, The Spetisbury'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run = $service->run($source, $second);
    $run->refresh();

    $priceLog = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run->id)
        ->where('field', 'price')
        ->firstOrFail();

    $runHref = route('sources.runs.show', [$source, $run]);
    $sourceHref = route('sources.show', $source);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Recent changes')
        ->assertSeeText('Dashboard Recent Changes Source')
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('plot:14')
        ->assertSeeText('price')
        ->assertSeeText((string) $priceLog->old_value)
        ->assertSeeText((string) $priceLog->new_value)
        ->assertSee('href="'.$sourceHref.'"', false)
        ->assertSee('href="'.$runHref.'"', false)
        ->assertSee('data-test="dashboard-recent-change-row"', false);
});

it('shows development overview groups from latest completed snapshot payloads with source links', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-dev-overview',
        'name' => 'Dashboard Dev Overview Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available', 'development' => 'Alpha Fields'],
    ]);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available', 'development' => 'Alpha Fields'],
        ['id' => 2, 'price' => 200_000, 'status' => 'reserved', 'development' => 'Alpha Fields'],
        ['id' => 3, 'price' => 300_000, 'status' => 'sold', 'development' => 'Beta Meadows'],
        ['id' => 4, 'price' => 400_000, 'status' => 'coming_soon', 'development' => 'Beta Meadows'],
        ['id' => 5, 'price' => 500_000, 'status' => 'available', 'development_name' => 'Gamma Rise'],
    ]);

    $sourceHref = route('sources.show', $source);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Development overview')
        ->assertSee('data-test="dashboard-development-overview"', false)
        ->assertDontSee('data-test="dashboard-development-overview-empty"', false)
        ->assertSeeText('Alpha Fields')
        ->assertSeeText('Beta Meadows')
        ->assertSeeText('Gamma Rise')
        ->assertSeeText('Coming soon')
        ->assertSee('href="'.$sourceHref.'"', false)
        ->assertSee('data-test="dashboard-development-overview-source-link"', false)
        ->getContent();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $rowNodes = $xpath->query("//*[@data-test='dashboard-development-overview-row']");
    expect($rowNodes)->not->toBeFalse();
    expect($rowNodes->length)->toBe(3);

    $rowCounts = [];
    foreach ($rowNodes as $row) {
        $development = trim((string) $xpath->evaluate(
            "string(.//*[@data-test='dashboard-development-overview-development'])",
            $row,
        ));
        $total = (int) $xpath->evaluate(
            "string(.//*[@data-test='dashboard-development-overview-total'])",
            $row,
        );
        $rowCounts[$development] = $total;
    }

    expect($rowCounts)->toBe([
        'Alpha Fields' => 2,
        'Beta Meadows' => 2,
        'Gamma Rise' => 1,
    ]);
});

it('groups missing development and unrecognised plot statuses safely in development overview', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-dev-fallbacks',
        'name' => 'Dashboard Dev Fallback Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'available', 'development' => ''],
        ['id' => 2, 'price' => 120_000, 'status' => 'not_a_status', 'development' => 'Known Site'],
        ['id' => 3, 'price' => 130_000, 'development' => 'Known Site'],
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Unknown development')
        ->assertSeeText('Known Site');
});

it('uses the latest completed snapshot per source for development overview when an older baseline exists', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-dev-latest-run',
        'name' => 'Dashboard Dev Latest Run Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available', 'development' => 'Stale Development'],
    ]);
    $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved', 'development' => 'Current Development'],
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $developmentNames = [];
    $nameNodes = $xpath->query("//*[@data-test='dashboard-development-overview-development']");
    foreach ($nameNodes as $node) {
        $developmentNames[] = trim((string) $node->textContent);
    }

    expect($developmentNames)->toBe(['Current Development']);
});

it('shows active issue info, warning, and error severity counts on the dashboard', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-active-severity',
        'name' => 'Dashboard Active Severity Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_sev_info',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'DASHBOARD_SEV_INFO',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_sev_info_ack',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
        'message' => 'DASHBOARD_SEV_INFO_ACK',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_sev_warning',
        'severity' => 'warning',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'DASHBOARD_SEV_WARNING',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_sev_error',
        'severity' => 'error',
        'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
        'message' => 'DASHBOARD_SEV_ERROR',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_sev_ignored_info',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_IGNORED,
        'message' => 'DASHBOARD_SEV_IGNORED_INFO',
    ]);
    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_sev_resolved_error',
        'severity' => 'error',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'DASHBOARD_SEV_RESOLVED_ERROR',
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-active-issues">\s*4\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-info">\s*2 info\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-warnings">\s*1 warnings\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-errors">\s*1 errors\s*</');
    expect($html)->toContain('data-test="dashboard-drill-active-info"');
    expect($html)->toContain('issue_status=active&amp;severity=info');
});

it('excludes ignored and resolved issues from dashboard summary counts and recent active issues', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $user = User::factory()->create();
        $source = MonitoredSource::create([
            'key' => 'hb:dash-active-filter',
            'name' => 'Dashboard Active Filter Source',
        ]);

        $run = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_active_open',
            'severity' => 'warning',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'DASHBOARD_ACTIVE_OPEN_ISSUE',
            'created_at' => now(),
        ]);
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_active_ack',
            'severity' => 'error',
            'status' => DatasetIssue::STATUS_ACKNOWLEDGED,
            'message' => 'DASHBOARD_ACTIVE_ACK_ISSUE',
            'created_at' => now(),
        ]);
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_inactive_ignored',
            'severity' => 'error',
            'status' => DatasetIssue::STATUS_IGNORED,
            'message' => 'DASHBOARD_INACTIVE_IGNORED_ISSUE',
            'created_at' => now(),
        ]);
        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_inactive_resolved',
            'severity' => 'warning',
            'status' => DatasetIssue::STATUS_RESOLVED,
            'message' => 'DASHBOARD_INACTIVE_RESOLVED_ISSUE',
            'created_at' => now(),
        ]);

        $html = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('DASHBOARD_ACTIVE_OPEN_ISSUE')
            ->assertSeeText('DASHBOARD_ACTIVE_ACK_ISSUE')
            ->assertDontSeeText('DASHBOARD_INACTIVE_IGNORED_ISSUE')
            ->assertDontSeeText('DASHBOARD_INACTIVE_RESOLVED_ISSUE')
            ->getContent();

        expect($html)->toMatch('/data-test="dashboard-active-issues">\s*2\s*</');
        expect($html)->toMatch('/data-test="dashboard-active-warnings">\s*1 warnings\s*</');
        expect($html)->toMatch('/data-test="dashboard-active-errors">\s*1 errors\s*</');
    } finally {
        Carbon::setTestNow();
    }
});

it('counts active issues regardless of age and links to the active issues filter', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $user = User::factory()->create();
        $source = MonitoredSource::create([
            'key' => 'hb:dash-old-active',
            'name' => 'Dashboard Old Active Source',
        ]);

        $run = DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'completed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDays(30),
            'finished_at' => now()->subDays(30),
        ]);

        $oldOpen = DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_old_active',
            'severity' => 'warning',
            'status' => DatasetIssue::STATUS_OPEN,
            'message' => 'DASHBOARD_OLD_ACTIVE_OPEN',
        ]);
        $oldOpen->forceFill(['created_at' => now()->subDays(30)])->save();

        DatasetIssue::create([
            'monitored_source_id' => $source->id,
            'dataset_comparison_run_id' => $run->id,
            'issue_type' => 'dash_old_inactive',
            'severity' => 'error',
            'status' => DatasetIssue::STATUS_RESOLVED,
            'message' => 'DASHBOARD_OLD_RESOLVED',
        ]);

        $html = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Active issues')
            ->assertDontSeeText('Active issues (7 days)')
            ->getContent();

        expect($html)->toMatch('/data-test="dashboard-active-issues">\s*1\s*</');
        expect($html)->toMatch('/data-test="dashboard-active-warnings">\s*1 warnings\s*</');
        expect($html)->toContain('href="'.route('issues.index', ['issue_status' => DatasetIssue::FILTER_ACTIVE]).'"');
        expect($html)->toContain('data-test="dashboard-drill-active-issues"');
    } finally {
        Carbon::setTestNow();
    }
});

it('does not link active issue drilldowns when there are no active issues', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-zero-active-drill',
        'name' => 'Dashboard Zero Active Drill Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_zero_active',
        'severity' => 'warning',
        'status' => DatasetIssue::STATUS_IGNORED,
        'message' => 'DASHBOARD_ONLY_IGNORED',
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-active-issues">\s*0\s*</');
    expect($html)->toContain('data-test="dashboard-active-issues-none"');
    expect($html)->not->toContain('data-test="dashboard-drill-active-issues"');
    expect($html)->toContain('data-test="dashboard-active-info-none"');
    expect($html)->toContain('data-test="dashboard-active-warnings-none"');
    expect($html)->not->toContain('data-test="dashboard-drill-active-warnings"');
});

it('shows an empty state for recent active issues when only ignored or resolved issues exist', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-inactive-only',
        'name' => 'Dashboard Inactive Only Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'dash_inactive_only',
        'severity' => 'info',
        'status' => DatasetIssue::STATUS_RESOLVED,
        'message' => 'DASHBOARD_INACTIVE_ONLY_ISSUE',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('data-test="dashboard-recent-issues-empty"', false)
        ->assertSeeText('No active issues')
        ->assertSeeText('Ignored and resolved issues are still available')
        ->assertDontSeeText('DASHBOARD_INACTIVE_ONLY_ISSUE');
});

it('does not count a recovered source_run_failed issue as an active dashboard error', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-recovered-run-failed-issue',
        'name' => 'Dashboard Recovered Run Failed Issue Source',
    ]);

    $failedRun = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subDay(),
        'finished_at' => now()->subDay(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $failedRun->id,
        'issue_type' => 'source_run_failed',
        'severity' => 'error',
        'message' => 'DASHBOARD_RECOVERED_SOURCE_RUN_FAILED',
    ]);

    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSeeText('DASHBOARD_RECOVERED_SOURCE_RUN_FAILED')
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-active-issues">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-errors">\s*0 errors\s*</');
    expect($html)->toContain('data-test="dashboard-active-issues-none"');
    expect($html)->not->toContain('ÔÇö');
});

it('counts a current source_run_failed issue as an active dashboard error when the latest run failed', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-current-run-failed-issue',
        'name' => 'Dashboard Current Run Failed Issue Source',
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
        'issue_type' => 'source_run_failed',
        'severity' => 'error',
        'message' => 'DASHBOARD_CURRENT_SOURCE_RUN_FAILED',
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('DASHBOARD_CURRENT_SOURCE_RUN_FAILED')
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-active-issues">\s*1\s*</');
    expect($html)->toMatch('/data-test="dashboard-active-errors">\s*1 errors\s*</');
    expect($html)->toContain('data-test="dashboard-drill-active-errors"');
});

it('shows a current failed run count when the latest run for a source failed', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-failed-current',
        'name' => 'Dashboard Current Failure Source',
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
    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-failed-runs-current">\s*1\s*</');
    expect($html)->toContain('data-test="dashboard-drill-failed-sources"');
    expect($html)->not->toContain('data-test="dashboard-failed-runs-recovered"');
});

it('shows a stale latest failed run as a current failure on the dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));
    try {
        $user = User::factory()->create();
        $source = MonitoredSource::create([
            'key' => 'hb:dash-failed-stale',
            'name' => 'Dashboard Stale Failure Source',
        ]);

        DatasetComparisonRun::create([
            'source_id' => $source->id,
            'status' => 'failed',
            'current_snapshot_id' => null,
            'previous_snapshot_id' => null,
            'summary' => null,
            'started_at' => now()->subDays(30),
            'finished_at' => now()->subDays(30),
        ]);

        $html = $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        expect($html)->toMatch('/data-test="dashboard-failed-runs-current">\s*1\s*</');
        expect($html)->toContain('Latest run still failed for one source');
    } finally {
        Carbon::setTestNow();
    }
});

it('formats dashboard timestamps in the schedule timezone', function () {
    config(['app.schedule_timezone' => 'Europe/London']);

    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-display-time',
        'name' => 'Dashboard Display Time Source',
    ]);

    $finishedAt = Carbon::parse('2026-05-14 05:42:00', 'UTC');
    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => $finishedAt->copy()->subMinute(),
        'finished_at' => $finishedAt,
    ]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('2026-05-14 06:42:00', false)
        ->assertDontSee('2026-05-14 05:42:00', false)
        ->getContent();

    expect($html)->not->toContain('ÔÇö');
});

it('orders recent comparison runs newest first by run id', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dash-run-order',
        'name' => 'Dashboard Run Order Source',
    ]);

    $older = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinutes(2),
        'finished_at' => now()->subMinute(),
    ]);
    $newer = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder([(string) $newer->id, (string) $older->id]);
});
