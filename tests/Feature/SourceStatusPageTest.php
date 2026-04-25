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
