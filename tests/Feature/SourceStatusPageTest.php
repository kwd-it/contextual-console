<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the source status page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/sources')
        ->assertOk();
});

it('shows an empty state when no monitored sources exist', function () {
    $this->actingAs(User::factory()->create())
        ->get('/sources')
        ->assertOk()
        ->assertSeeText('No monitored sources found.');
});

it('shows a source with no runs', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-no-runs',
        'name' => 'Page No Runs',
    ]);

    $this->actingAs($user)
        ->get('/sources')
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key)
        ->assertSeeText('none')
        ->assertSeeText('0');
});

it('links a source name to the source detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-link-detail',
        'name' => 'Page Link Detail',
    ]);

    $this->actingAs($user)
        ->get('/sources')
        ->assertOk()
        ->assertSee('href="'.route('sources.show', $source).'"', false)
        ->assertSeeText($source->name);
});

it('loads the source detail page successfully', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-loads',
        'name' => 'Page Detail Loads',
    ]);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key);
});

it('shows a source with no runs on the detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-no-runs',
        'name' => 'Page Detail No Runs',
    ]);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key)
        ->assertSeeText('No runs found for this source.');
});

it('shows latest run summary and recent runs on the detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-recent-runs',
        'name' => 'Page Detail Recent Runs',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // added
    ];

    $service = app(PlotDatasetRunService::class);
    $run1 = $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    expect($run2->status)->toBe('completed');

    $added = (int) ($run2->summary['added'] ?? 0);
    $changed = (int) ($run2->summary['changed'] ?? 0);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run summary')
        ->assertSeeText('Recent runs')
        ->assertSeeText('completed')
        ->assertSeeText((string) $run2->id)
        ->assertSeeText("added={$added}")
        ->assertSeeText("changed={$changed}")
        ->assertSeeText((string) $run1->id);
});

it('shows latest run issues on the detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-latest-issues',
        'name' => 'Page Detail Latest Issues',
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

    expect($run2->status)->toBe('completed');

    $issue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->orderByDesc('id')
        ->first();

    expect($issue)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run issues')
        ->assertSeeText((string) $issue->severity)
        ->assertSeeText((string) $issue->issue_type)
        ->assertSeeText((string) $issue->message);
});

it('does not show old-run issues as latest-run issues', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-old-issues',
        'name' => 'Page Detail Old Issues',
    ]);

    $bad = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record',
    ];

    $good = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $run1 = $service->run($source, $bad);
    $run1->refresh();

    $oldIssueMessage = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run1->id)
        ->orderByDesc('id')
        ->value('message');

    expect($oldIssueMessage)->not->toBeNull();

    $run2 = $service->run($source, $good);
    $run2->refresh();

    $latestIssues = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->count();

    expect($latestIssues)->toBe(0);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('No issues found for the latest run.')
        ->assertDontSeeText((string) $oldIssueMessage);
});

it('shows linked latest-run changes on the detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-latest-run-changes',
        'name' => 'Page Detail Latest Run Changes',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // added => presence change
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    $changes = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->orderBy('entity_id')
        ->orderBy('field')
        ->get();

    expect($changes->count())->toBeGreaterThan(0);

    $sample = $changes->first();
    expect($sample)->not->toBeNull();

    $resp = $this->actingAs($user)->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run changes')
        ->assertSeeText((string) $sample->entity_id)
        ->assertSeeText((string) $sample->field);

    if ($sample->old_value !== null) {
        $resp->assertSeeText((string) $sample->old_value);
    }

    if ($sample->new_value !== null) {
        $resp->assertSeeText((string) $sample->new_value);
    }
});

it('shows snapshot-derived plot title and development for latest run changes and plot issues', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-plot-labels',
        'name' => 'Page Detail Plot Labels',
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
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run changes')
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('Charminster Farm')
        ->assertSeeText('Technical ID: plot:14')
        ->assertSeeText('Latest run issues')
        ->assertSeeText('Plot status is invalid.');
});

it('does not show old-run changes as latest-run changes', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-old-run-changes',
        'name' => 'Page Detail Old Run Changes',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);

    // Run 2 introduces a status change that should NOT show in the latest run changes.
    $run2 = $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'coming_soon'], // changed (status + price)
    ]);
    $run2->refresh();

    $oldRunChangeNewValue = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('field', 'status')
        ->orderByDesc('id')
        ->value('new_value');

    expect($oldRunChangeNewValue)->not->toBeNull();

    // Run 3 only changes price; status remains the same, so the old status change should not appear.
    $run3 = $service->run($source, [
        ['id' => 1, 'price' => 120_000, 'status' => 'coming_soon'], // changed (price only)
    ]);
    $run3->refresh();

    $latestRunChangesCount = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run3->id)
        ->count();

    expect($latestRunChangesCount)->toBeGreaterThan(0);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run changes')
        ->assertDontSeeText((string) $oldRunChangeNewValue);
});

it('shows an empty state when latest run has no linked changes', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-latest-run-no-changes',
        'name' => 'Page Detail Latest Run No Changes',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $changed = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);

    $run2 = $service->run($source, $changed);
    $run2->refresh();

    $run2ChangeCount = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->count();

    expect($run2ChangeCount)->toBeGreaterThan(0);

    // Latest run repeats the same dataset, producing no change logs.
    $run3 = $service->run($source, $changed);
    $run3->refresh();

    $run3ChangeCount = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run3->id)
        ->count();

    expect($run3ChangeCount)->toBe(0);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run changes')
        ->assertSeeText('No changes found for the latest run.');
});

it('shows latest completed run summary and issue counts', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-completed-issues',
        'name' => 'Page Completed Issues',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $second = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'], // changed
        ['id' => 2, 'price' => 200_000, 'status' => 'available'], // added
        'bad-record', // error: invalid_record
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    expect($run2->status)->toBe('completed');

    $totalIssues = DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->count();
    $errorIssues = DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->where('severity', 'error')->count();
    $warningIssues = DatasetIssue::query()->where('dataset_comparison_run_id', $run2->id)->where('severity', 'warning')->count();

    expect($totalIssues)->toBeGreaterThan(0);
    expect($errorIssues)->toBeGreaterThan(0);

    $added = (int) ($run2->summary['added'] ?? 0);
    $changed = (int) ($run2->summary['changed'] ?? 0);

    expect($added)->toBeGreaterThan(0);
    expect($changed)->toBeGreaterThan(0);

    $resp = $this->actingAs($user)->get('/sources')
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText('completed')
        ->assertSeeText("added={$added}")
        ->assertSeeText("changed={$changed}")
        ->assertSeeText((string) $totalIssues);

    $resp->assertSeeText("error={$errorIssues}");

    if ($warningIssues > 0) {
        $resp->assertSeeText("warning={$warningIssues}");
    }
});

it('shows a failed latest run safely on the source list page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-failed-run-list',
        'name' => 'Page Failed Run List',
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
        'issue_type' => 'source_run_failed',
        'severity' => 'error',
        'message' => 'Source run failed',
        'context' => ['reason' => 'boom'],
    ]);

    $this->actingAs($user)
        ->get('/sources')
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key)
        ->assertSeeText('failed')
        ->assertSeeText("Run ID: {$run->id}")
        ->assertSeeText('Current snapshot ID: -')
        ->assertSeeText('added=0')
        ->assertSeeText('removed=0')
        ->assertSeeText('changed=0')
        ->assertSeeText('unchanged=0');
});

it('shows a failed latest run and its source_run_failed issue on the source detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:page-failed-run-detail',
        'name' => 'Page Failed Run Detail',
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
        'message' => 'Source run failed hard',
        'context' => ['reason' => 'timeout'],
    ]);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run summary')
        ->assertSeeText('failed')
        ->assertSeeText((string) $run->id)
        ->assertSeeText('Current snapshot id')
        ->assertSeeText('-')
        ->assertSeeText('added=0')
        ->assertSeeText('removed=0')
        ->assertSeeText('changed=0')
        ->assertSeeText('unchanged=0')
        ->assertSeeText('Latest run issues')
        ->assertSeeText((string) $issue->severity)
        ->assertSeeText((string) $issue->issue_type)
        ->assertSeeText((string) $issue->message);
});
