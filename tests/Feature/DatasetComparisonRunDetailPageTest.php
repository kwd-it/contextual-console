<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('links each recent run id on the source detail page to its run detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-link',
        'name' => 'Run Detail Link Source',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $run1 = $service->run($source, $baseline);
    $run2 = $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ]);
    $run2->refresh();

    $href1 = route('sources.runs.show', [$source, $run1]);
    $href2 = route('sources.runs.show', [$source, $run2]);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSee('href="'.$href1.'"', false)
        ->assertSee('href="'.$href2.'"', false);
});

it('loads the run detail page for an authenticated user', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-loads',
        'name' => 'Run Detail Loads Source',
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

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $run]))
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key)
        ->assertSeeText('Run overview')
        ->assertSeeText((string) $run->id)
        ->assertSeeText('failed');
});

it('redirects unauthenticated users from the run detail page to login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-auth',
        'name' => 'Run Detail Auth Source',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'baseline',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $this->get(route('sources.runs.show', [$source, $run]))
        ->assertRedirect(route('login'));
});

it('shows issues linked to that run on the run detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-issues',
        'name' => 'Run Detail Issues Source',
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
        'issue_type' => 'source_run_failed',
        'severity' => 'error',
        'message' => 'Run-specific failure message',
        'context' => ['reason' => 'timeout'],
    ]);

    $showHref = route('issues.show', $issue);

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $run]))
        ->assertOk()
        ->assertSeeText('Issues on this run')
        ->assertSeeText((string) $issue->severity)
        ->assertSeeText((string) $issue->issue_type)
        ->assertSeeText((string) $issue->message)
        ->assertSee('href="'.$showHref.'"', false)
        ->assertSee('data-test="run-issue-message-link"', false);
});

it('shows change logs linked to that run on the run detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-changes',
        'name' => 'Run Detail Changes Source',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ]);
    $run2->refresh();

    $changes = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->orderBy('entity_id')
        ->orderBy('field')
        ->get();

    expect($changes->count())->toBeGreaterThan(0);
    $sample = $changes->first();
    expect($sample)->not->toBeNull();

    $resp = $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $run2]))
        ->assertOk()
        ->assertSeeText('Plot data changes on this run')
        ->assertSeeText((string) $sample->entity_id)
        ->assertSeeText((string) $sample->field);

    if ($sample->old_value !== null) {
        $resp->assertSeeText((string) $sample->old_value);
    }

    if ($sample->new_value !== null) {
        $resp->assertSeeText((string) $sample->new_value);
    }
});

it('shows issues and changes for an older run when that run is not the latest', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-old-run',
        'name' => 'Run Detail Old Run Source',
    ]);

    $bad = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record',
    ];

    $good = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $runIssuesOnly = $service->run($source, $bad);
    $runIssuesOnly->refresh();

    $oldIssueMessage = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $runIssuesOnly->id)
        ->orderByDesc('id')
        ->value('message');

    expect($oldIssueMessage)->not->toBeNull();

    $runClean = $service->run($source, $good);
    $runClean->refresh();

    expect(DatasetIssue::query()->where('dataset_comparison_run_id', $runClean->id)->count())->toBe(0);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('No issues found for the latest run.')
        ->assertDontSeeText((string) $oldIssueMessage);

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $runIssuesOnly]))
        ->assertOk()
        ->assertSeeText('Issues on this run')
        ->assertSeeText((string) $oldIssueMessage);

    // Older run change logs: run2 introduces a status change; run3 is latest and does not surface that value on the source page.
    $sourceB = MonitoredSource::create([
        'key' => 'hb:run-detail-old-changes',
        'name' => 'Run Detail Old Changes Source',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $service->run($sourceB, $baseline);

    $runWithStatusChange = $service->run($sourceB, [
        ['id' => 1, 'price' => 110_000, 'status' => 'coming_soon'],
    ]);
    $runWithStatusChange->refresh();

    $oldRunChangeNewValue = ChangeLog::query()
        ->where('dataset_comparison_run_id', $runWithStatusChange->id)
        ->where('field', 'status')
        ->orderByDesc('id')
        ->value('new_value');

    expect($oldRunChangeNewValue)->not->toBeNull();

    $runLatest = $service->run($sourceB, [
        ['id' => 1, 'price' => 120_000, 'status' => 'coming_soon'],
    ]);
    $runLatest->refresh();

    expect(ChangeLog::query()->where('dataset_comparison_run_id', $runLatest->id)->count())->toBeGreaterThan(0);

    $this->actingAs($user)
        ->get(route('sources.show', $sourceB))
        ->assertOk()
        ->assertSeeText('Plot data changes on this run')
        ->assertDontSeeText((string) $oldRunChangeNewValue);

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$sourceB, $runWithStatusChange]))
        ->assertOk()
        ->assertSeeText('Plot data changes on this run')
        ->assertSeeText((string) $oldRunChangeNewValue);
});

it('shows old and new values for plot status and price change issues on the run detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-change-issues',
        'name' => 'Run Detail Change Issues Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $run2 = $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    ]);
    $run2->refresh();

    expect(DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('issue_type', PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED)
        ->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $run2]))
        ->assertOk()
        ->assertSeeText('Issues on this run')
        ->assertSeeText('Plot status changed.')
        ->assertSeeText('available -> reserved')
        ->assertSeeText('Plot price changed.')
        ->assertSeeText('100000 -> 110000')
        ->assertSee('data-test="issue-change-detail"', false);
});

it('shows snapshot-derived plot title and development on the run detail page when available', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-detail-plot-labels',
        'name' => 'Run Detail Plot Labels Source',
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

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $run2]))
        ->assertOk()
        ->assertSeeText('Plot data changes on this run')
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('Charminster Farm')
        ->assertSeeText('Technical ID: plot:14')
        ->assertSeeText('Issues on this run')
        ->assertSeeText('Plot status is invalid.');
});

it('returns not found when the run belongs to a different source', function () {
    $user = User::factory()->create();
    $sourceA = MonitoredSource::create([
        'key' => 'hb:run-detail-scope-a',
        'name' => 'Scope A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:run-detail-scope-b',
        'name' => 'Scope B',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $sourceA->id,
        'status' => 'baseline',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$sourceB, $run]))
        ->assertNotFound();
});
