<?php

use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows no monitored sources when none exist', function () {
    $this->artisan('contextual-console:source-status')
        ->expectsOutputToContain('No monitored sources found.')
        ->assertExitCode(0);
});

it('shows a source with no runs', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:cmd-no-runs',
        'name' => 'Command No Runs',
    ]);

    $this->artisan('contextual-console:source-status')
        ->expectsOutputToContain("Source: {$source->name}")
        ->expectsOutputToContain("Key: {$source->key}")
        ->expectsOutputToContain('Latest run: none')
        ->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);
});

it('shows latest run status and summary counts', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:cmd-summary',
        'name' => 'Command Summary',
    ]);

    $baseline = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];
    $changed = [
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
        ['id' => 2, 'price' => 200_000, 'status' => 'available'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $changed);
    $run2->refresh();

    $added = (int) ($run2->summary['added'] ?? 0);
    $removed = (int) ($run2->summary['removed'] ?? 0);
    $changedCount = (int) ($run2->summary['changed'] ?? 0);
    $unchanged = (int) ($run2->summary['unchanged'] ?? 0);

    $this->artisan('contextual-console:source-status')
        ->expectsOutputToContain('Latest run: completed')
        ->expectsOutputToContain("Run ID: {$run2->id}")
        ->expectsOutputToContain("Summary: added={$added} removed={$removed} changed={$changedCount} unchanged={$unchanged}")
        ->assertExitCode(0);
});

it('shows issue counts and severity counts', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:cmd-issues',
        'name' => 'Command Issues',
    ]);

    $invalidPayload = [
        'bad-record',
        ['price' => 100_000, 'status' => 'available'], // missing id
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $run = app(PlotDatasetRunService::class)->run($source, $invalidPayload);
    $run->refresh();

    $total = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->count();
    $errors = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->where('severity', 'error')->count();
    $warnings = DatasetIssue::query()->where('dataset_comparison_run_id', $run->id)->where('severity', 'warning')->count();

    expect($total)->toBeGreaterThan(0);
    expect($errors)->toBeGreaterThan(0);

    $artisan = $this->artisan('contextual-console:source-status')
        ->expectsOutputToContain("Issues: {$total}")
        ->expectsOutputToContain("- error: {$errors}");

    if ($warnings > 0) {
        $artisan->expectsOutputToContain("- warning: {$warnings}");
    }

    $artisan->assertExitCode(0);
});
