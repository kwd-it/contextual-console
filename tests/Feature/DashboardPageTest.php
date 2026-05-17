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

        $html = $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $href = static fn (string $dataTest): string => (string) $xpath->evaluate("string(//a[@data-test='{$dataTest}']/@href)");

        expect($href('dashboard-drill-issues-7d'))->toBe(route('issues.index', ['date_from' => $expectedDateFrom]));
        expect($href('dashboard-drill-warnings-7d'))->toBe(route('issues.index', [
            'date_from' => $expectedDateFrom,
            'severity' => 'warning',
        ]));
        expect($href('dashboard-drill-errors-7d'))->toBe(route('issues.index', [
            'date_from' => $expectedDateFrom,
            'severity' => 'error',
        ]));
        expect($href('dashboard-drill-changes-7d'))->toBe(route('changes.index', ['date_from' => $expectedDateFrom]));
        expect($href('dashboard-drill-sources-total'))->toBe(route('sources.index'));
        expect($href('dashboard-drill-sources-with-runs'))->toBe(route('sources.index'));
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
        ->assertSeeText('No issues recorded')
        ->assertSeeText('Comparison runs will appear here after snapshots are ingested and compared.')
        ->assertSee('data-test="dashboard-development-overview-empty"', false)
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-total-sources">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-sources-with-runs">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-failed-runs-7d">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-issues-7d">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-warnings-7d">\s*0\s*</');
    expect($html)->toMatch('/data-test="dashboard-errors-7d">\s*0\s*</');
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
    $latestRun = $service->run($active, [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ]);
    $latestRun->refresh();

    DatasetComparisonRun::create([
        'source_id' => $active->id,
        'status' => 'failed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    $failedRun = DatasetComparisonRun::query()
        ->where('source_id', $active->id)
        ->where('status', 'failed')
        ->firstOrFail();

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
        ->assertSeeText('Recent issues')
        ->assertSee('href="'.$latestRunHref.'"', false)
        ->assertSee('href="'.$failedHref.'"', false)
        ->assertSeeText('DASHBOARD_RECENT_ISSUE_WARNING')
        ->assertSeeText('DASHBOARD_RECENT_ISSUE_ERROR')
        ->getContent();

    expect($html)->toMatch('/data-test="dashboard-total-sources">\s*2\s*</');
    expect($html)->toMatch('/data-test="dashboard-sources-with-runs">\s*1\s*</');
    expect($html)->toMatch('/data-test="dashboard-failed-runs-7d">\s*1\s*</');
    expect($html)->toMatch('/data-test="dashboard-issues-7d">\s*4\s*</');
    expect($html)->toMatch('/data-test="dashboard-warnings-7d">\s*1\s*</');
    expect($html)->toMatch('/data-test="dashboard-errors-7d">\s*1\s*</');
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
