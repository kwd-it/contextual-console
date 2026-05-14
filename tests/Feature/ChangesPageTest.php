<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from /changes to /login', function () {
    $this->get('/changes')
        ->assertRedirect(route('login'));
});

it('allows authenticated users to load /changes', function () {
    $this->actingAs(User::factory()->create())
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('All changes');
});

it('shows sources, changes, and issues links in the dashboard navigation', function () {
    $user = User::factory()->create();

    $sourcesHref = route('sources.index');
    $changesHref = route('changes.index');
    $issuesHref = route('issues.index');

    $this->actingAs($user)
        ->get('/sources')
        ->assertOk()
        ->assertSee('href="'.$sourcesHref.'"', false)
        ->assertSee('href="'.$changesHref.'"', false)
        ->assertSee('href="'.$issuesHref.'"', false)
        ->assertSeeText('Changes');

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSee('href="'.$sourcesHref.'"', false)
        ->assertSee('href="'.$changesHref.'"', false)
        ->assertSee('href="'.$issuesHref.'"', false);

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSee('href="'.$changesHref.'"', false);
});

it('shows changes from multiple sources with source names and keys', function () {
    $user = User::factory()->create();

    $sourceA = MonitoredSource::create([
        'key' => 'hb:changes-multi-a',
        'name' => 'Changes Multi Source A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:changes-multi-b',
        'name' => 'Changes Multi Source B',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($sourceA, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $service->run($sourceA, [
        ['id' => 1, 'price' => 110_000, 'status' => 'available'],
    ]);

    $service->run($sourceB, [
        ['id' => 1, 'price' => 200_000, 'status' => 'available'],
    ]);
    $service->run($sourceB, [
        ['id' => 1, 'price' => 210_000, 'status' => 'available'],
    ]);

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('Changes Multi Source A')
        ->assertSeeText('hb:changes-multi-a')
        ->assertSeeText('Changes Multi Source B')
        ->assertSeeText('hb:changes-multi-b');
});

it('lists newest changes first by changed_at', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-order',
        'name' => 'Changes Order Source',
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

    $older = ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 1,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_PAGE_ORDER_OLDER',
        'new_value' => '1',
        'changed_at' => now()->subDays(3),
    ]);
    $older->forceFill([
        'changed_at' => now()->subDays(3),
    ])->save();

    $newer = ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 2,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_PAGE_ORDER_NEWER',
        'new_value' => '2',
        'changed_at' => now()->subDay(),
    ]);
    $newer->forceFill([
        'changed_at' => now()->subDay(),
    ])->save();

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeInOrder([
            'CHANGES_PAGE_ORDER_NEWER',
            'CHANGES_PAGE_ORDER_OLDER',
        ]);
});

it('links run ids to the existing comparison run detail page when a run is linked', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-run-link',
        'name' => 'Changes Run Link Source',
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

    $href = route('sources.runs.show', [$source, $run2]);

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSee('href="'.$href.'"', false);
});

it('does not emit a run detail link when the change log has no linked run', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-no-run',
        'name' => 'Changes No Run Source',
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

    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 7,
        'dataset_comparison_run_id' => null,
        'field' => 'price',
        'old_value' => 'CHANGES_PAGE_NO_RUN_MARKER',
        'new_value' => '999',
        'changed_at' => now(),
    ]);

    $noLinkHref = route('sources.runs.show', [$source, $run]);

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('CHANGES_PAGE_NO_RUN_MARKER')
        ->assertDontSee('href="'.$noLinkHref.'"', false);
});

it('shows snapshot-derived plot labels and keeps technical ids visible', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-plot-labels',
        'name' => 'Changes Plot Labels Source',
    ]);

    $baseline = [
        ['id' => 14, 'price' => 100_000, 'status' => 'available', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
    ];
    $second = [
        ['id' => 14, 'price' => 110_000, 'status' => 'reserved', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $run2 = $service->run($source, $second);
    $run2->refresh();

    expect($run2->status)->toBe('completed');

    $priceLog = ChangeLog::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('entity_type', 'plot')
        ->where('entity_id', 14)
        ->where('field', 'price')
        ->first();
    expect($priceLog)->not->toBeNull();

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('Charminster Farm')
        ->assertSeeText('Technical ID: plot:14')
        ->assertSeeText('Change log id: '.(string) $priceLog->id);
});
