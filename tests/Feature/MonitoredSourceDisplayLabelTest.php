<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\MonitoredSourceStatusService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses display_name in source status summaries when set', function () {
    $source = MonitoredSource::create([
        'key' => 'wyatt:housebuilder',
        'name' => 'Wyatt Homes Housebuilder',
        'display_name' => 'Wyatt Homes',
    ]);

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = collect($summaries)->firstWhere('source_key', $source->key);

    expect($summary)->not->toBeNull();
    expect($summary['source_name'])->toBe('Wyatt Homes');
    expect($summary['source_key'])->toBe('wyatt:housebuilder');
});

it('falls back to name in source status summaries when display_name is unset', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:no-display-name',
        'name' => 'Internal Source Name',
    ]);

    $summaries = app(MonitoredSourceStatusService::class)->summaries();
    $summary = collect($summaries)->firstWhere('source_key', $source->key);

    expect($summary)->not->toBeNull();
    expect($summary['source_name'])->toBe('Internal Source Name');
});

it('shows display label on the sources list while keeping the source key visible', function () {
    $source = MonitoredSource::create([
        'key' => 'wyatt:housebuilder',
        'name' => 'Wyatt Homes Housebuilder',
        'display_name' => 'Wyatt Homes',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/sources')
        ->assertOk()
        ->assertSeeText('Wyatt Homes')
        ->assertDontSeeText('Wyatt Homes Housebuilder')
        ->assertSeeText('wyatt:housebuilder');
});

it('shows display label on the source detail heading while keeping the source key visible', function () {
    $source = MonitoredSource::create([
        'key' => 'wyatt:housebuilder',
        'name' => 'Wyatt Homes Housebuilder',
        'display_name' => 'Wyatt Homes',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Wyatt Homes')
        ->assertDontSeeText('Wyatt Homes Housebuilder')
        ->assertSeeText('Source key: wyatt:housebuilder');
});

it('shows display label on dashboard, issues, changes, and run detail pages', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'wyatt:housebuilder',
        'name' => 'Wyatt Homes Housebuilder',
        'display_name' => 'Wyatt Homes',
    ]);

    $run = DatasetComparisonRun::create([
        'source_id' => $source->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => ['added' => 0, 'removed' => 0, 'changed' => 1, 'unchanged' => 0],
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    ChangeLog::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'entity_type' => 'plot',
        'entity_id' => '101',
        'field' => 'price',
        'old_value' => '100000',
        'new_value' => '110000',
        'changed_at' => now(),
    ]);

    DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'display_label_test',
        'severity' => 'warning',
        'status' => DatasetIssue::STATUS_OPEN,
        'message' => 'DISPLAY_LABEL_TEST_ISSUE',
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('Wyatt Homes')
        ->assertDontSeeText('Wyatt Homes Housebuilder');

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('Wyatt Homes')
        ->assertDontSeeText('Wyatt Homes Housebuilder');

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Wyatt Homes')
        ->assertDontSeeText('Wyatt Homes Housebuilder');

    $this->actingAs($user)
        ->get(route('sources.runs.show', [$source, $run]))
        ->assertOk()
        ->assertSeeText('Wyatt Homes')
        ->assertDontSeeText('Wyatt Homes Housebuilder')
        ->assertSeeText('Source key: wyatt:housebuilder');
});

it('uses source name on UI pages when display_name is not set', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:fallback-ui',
        'name' => 'Fallback UI Source Name',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText('Fallback UI Source Name')
        ->assertSeeText('Source key: hb:fallback-ui');
});
