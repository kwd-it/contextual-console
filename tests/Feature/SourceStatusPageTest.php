<?php

use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the source status page', function () {
    $this->get('/sources')->assertOk();
});

it('shows an empty state when no monitored sources exist', function () {
    $this->get('/sources')
        ->assertOk()
        ->assertSeeText('No monitored sources found.');
});

it('shows a source with no runs', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:page-no-runs',
        'name' => 'Page No Runs',
    ]);

    $this->get('/sources')
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key)
        ->assertSeeText('none')
        ->assertSeeText('0');
});

it('links a source name to the source detail page', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:page-link-detail',
        'name' => 'Page Link Detail',
    ]);

    $this->get('/sources')
        ->assertOk()
        ->assertSee('href="' . route('sources.show', $source) . '"', false)
        ->assertSeeText($source->name);
});

it('loads the source detail page successfully', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-loads',
        'name' => 'Page Detail Loads',
    ]);

    $this->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key);
});

it('shows a source with no runs on the detail page', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:page-detail-no-runs',
        'name' => 'Page Detail No Runs',
    ]);

    $this->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText($source->name)
        ->assertSeeText($source->key)
        ->assertSeeText('No runs found for this source.');
});

it('shows latest run summary and recent runs on the detail page', function () {
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

    $this->get(route('sources.show', $source))
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

    $this->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Latest run issues')
        ->assertSeeText((string) $issue->severity)
        ->assertSeeText((string) $issue->issue_type)
        ->assertSeeText((string) $issue->message);
});

it('does not show old-run issues as latest-run issues', function () {
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

    $this->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('No issues found for the latest run.')
        ->assertDontSeeText((string) $oldIssueMessage);
});

it('shows latest completed run summary and issue counts', function () {
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

    $resp = $this->get('/sources')
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
