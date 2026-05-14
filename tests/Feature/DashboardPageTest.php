<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
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
        ->assertSeeText('Summary of monitored website datasets');
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
        ->assertSeeText('No runs yet')
        ->assertSeeText('No issues recorded')
        ->assertSeeText('Comparison runs will appear here after snapshots are ingested and compared.')
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
