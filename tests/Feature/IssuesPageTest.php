<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from /issues to /login', function () {
    $this->get('/issues')
        ->assertRedirect(route('login'));
});

it('allows authenticated users to load /issues', function () {
    $this->actingAs(User::factory()->create())
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Issues')
        ->assertSeeText('Recent issues from daily dataset comparisons across all sources');
});

it('links to the issues page from the sources index navigation', function () {
    $href = route('issues.index');

    $this->actingAs(User::factory()->create())
        ->get('/sources')
        ->assertOk()
        ->assertSee('href="'.$href.'"', false);
});

it('shows issues from multiple sources with source names and keys', function () {
    $user = User::factory()->create();

    $sourceA = MonitoredSource::create([
        'key' => 'hb:issues-multi-a',
        'name' => 'Issues Multi Source A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:issues-multi-b',
        'name' => 'Issues Multi Source B',
    ]);

    $badA = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record-a',
    ];
    $badB = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        'bad-record-b',
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($sourceA, $badA);
    $service->run($sourceB, $badB);

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Issues Multi Source A')
        ->assertSeeText('hb:issues-multi-a')
        ->assertSeeText('Issues Multi Source B')
        ->assertSeeText('hb:issues-multi-b');
});

it('lists newest issues first by recorded time', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-order',
        'name' => 'Issues Order Source',
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

    $older = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'older_marker',
        'severity' => 'info',
        'message' => 'ISSUES_PAGE_ORDER_OLDER',
    ]);
    $older->forceFill([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ])->save();

    $newer = DatasetIssue::create([
        'monitored_source_id' => $source->id,
        'dataset_snapshot_id' => null,
        'dataset_comparison_run_id' => $run->id,
        'issue_type' => 'newer_marker',
        'severity' => 'info',
        'message' => 'ISSUES_PAGE_ORDER_NEWER',
    ]);
    $newer->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->save();

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeInOrder([
            'ISSUES_PAGE_ORDER_NEWER',
            'ISSUES_PAGE_ORDER_OLDER',
        ]);
});

it('links run ids to the existing comparison run detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-run-link',
        'name' => 'Issues Run Link Source',
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

    $href = route('sources.runs.show', [$source, $run2]);

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSee('href="'.$href.'"', false);
});

it('shows snapshot-derived plot labels and keeps technical ids visible', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:issues-plot-labels',
        'name' => 'Issues Plot Labels Source',
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

    $plotIssue = DatasetIssue::query()
        ->where('dataset_comparison_run_id', $run2->id)
        ->where('entity_type', 'plot')
        ->first();
    expect($plotIssue)->not->toBeNull();

    $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSeeText('Plot 14, The Spetisbury')
        ->assertSeeText('Charminster Farm')
        ->assertSeeText('Technical ID: plot:14')
        ->assertSeeText('Issue id: '.(string) $plotIssue->id)
        ->assertSeeText('Plot status is invalid.');
});
