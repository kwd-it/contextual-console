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
        ->assertSeeText('Plot data changes')
        ->assertSeeText('Recent field-level changes detected from daily dataset comparisons across all sources');
});

it('shows sources, changes, and issues links in the dashboard navigation', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:nav-active-source',
        'name' => 'Nav Active Source',
    ]);

    $dashboardHref = route('dashboard.index');
    $sourcesHref = route('sources.index');
    $changesHref = route('changes.index');
    $issuesHref = route('issues.index');

    $sourcesIndex = $this->actingAs($user)
        ->get('/sources')
        ->assertOk()
        ->assertSee('href="'.$dashboardHref.'"', false)
        ->assertSee('href="'.$sourcesHref.'"', false)
        ->assertSee('href="'.$changesHref.'"', false)
        ->assertSee('href="'.$issuesHref.'"', false)
        ->assertSeeText('Dashboard')
        ->assertSeeText('Changes')
        ->assertSeeText('Contextual Console')
        ->assertSee('Website data monitoring', false);

    expect(substr_count($sourcesIndex->getContent(), 'aria-current="page"'))->toBe(1);

    $changesIndex = $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSee('href="'.$dashboardHref.'"', false)
        ->assertSee('href="'.$sourcesHref.'"', false)
        ->assertSee('href="'.$changesHref.'"', false)
        ->assertSee('href="'.$issuesHref.'"', false);

    expect(substr_count($changesIndex->getContent(), 'aria-current="page"'))->toBe(1);

    $issuesIndex = $this->actingAs($user)
        ->get('/issues')
        ->assertOk()
        ->assertSee('href="'.$dashboardHref.'"', false)
        ->assertSee('href="'.$changesHref.'"', false);

    expect(substr_count($issuesIndex->getContent(), 'aria-current="page"'))->toBe(1);

    $sourceShow = $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk();

    expect(substr_count($sourceShow->getContent(), 'aria-current="page"'))->toBe(1);
});

it('shows changes from multiple sources with source display labels only', function () {
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
        ->assertSeeText('Changes Multi Source B')
        ->assertDontSeeText('hb:changes-multi-a')
        ->assertDontSeeText('hb:changes-multi-b');
});

it('shows a user-focused changes table without the source key column', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-table-ui',
        'name' => 'Changes Table UI Source',
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

    $html = $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('Changes Table UI Source')
        ->assertSee('href="'.$href.'"', false)
        ->getContent();

    expect($html)->toContain('<th>Source</th>');
    expect($html)->toContain('<th>Entity</th>');
    expect($html)->toContain('<th>Field</th>');
    expect($html)->toContain('<th>Old value</th>');
    expect($html)->toContain('<th>New value</th>');
    expect($html)->toContain('<th>Changed at</th>');
    expect($html)->toContain('<th>Run</th>');
    expect($html)->not->toContain('<th>Source key</th>');
    expect($html)->not->toContain('hb:changes-table-ui');
    expect($html)->toMatch('/<th>Source<\/th>[\s\S]*<th>Entity<\/th>[\s\S]*<th>Field<\/th>[\s\S]*<th>Old value<\/th>[\s\S]*<th>New value<\/th>[\s\S]*<th>Changed at<\/th>[\s\S]*<th>Run<\/th>/');
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

it('shows last modified by from snapshot payloads when available', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-last-modified-by',
        'name' => 'Changes Last Modified By Source',
    ]);

    $baseline = [
        ['id' => 14, 'price' => 100_000, 'status' => 'available', 'title' => 'Plot 14', 'last_modified_by' => 'mark'],
    ];
    $second = [
        ['id' => 14, 'price' => 110_000, 'status' => 'available', 'title' => 'Plot 14', 'last_modified_by' => 'kirk'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $service->run($source, $second);

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('Last modified by: kirk')
        ->assertSeeText('Plot 14');
});

it('does not show last modified by text when snapshot metadata is missing', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-no-last-modified-by',
        'name' => 'Changes No Last Modified By Source',
    ]);

    $baseline = [
        ['id' => 14, 'price' => 100_000, 'status' => 'available', 'title' => 'Plot 14'],
    ];
    $second = [
        ['id' => 14, 'price' => 110_000, 'status' => 'available', 'title' => 'Plot 14'],
    ];

    $service = app(PlotDatasetRunService::class);
    $service->run($source, $baseline);
    $service->run($source, $second);

    $this->actingAs($user)
        ->get('/changes')
        ->assertOk()
        ->assertSeeText('Plot 14')
        ->assertDontSee('Last modified by:');
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

it('filters changes by source via GET query', function () {
    $user = User::factory()->create();

    $sourceA = MonitoredSource::create([
        'key' => 'hb:changes-filter-src-a',
        'name' => 'Changes Filter Source A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:changes-filter-src-b',
        'name' => 'Changes Filter Source B',
    ]);

    $runA = DatasetComparisonRun::create([
        'source_id' => $sourceA->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);
    $runB = DatasetComparisonRun::create([
        'source_id' => $sourceB->id,
        'status' => 'completed',
        'current_snapshot_id' => null,
        'previous_snapshot_id' => null,
        'summary' => null,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
    ]);

    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 1,
        'dataset_comparison_run_id' => $runA->id,
        'field' => 'price',
        'old_value' => 'CHANGES_FILTER_SRC_A_MARKER',
        'new_value' => '1',
        'changed_at' => now()->subHour(),
    ]);
    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 2,
        'dataset_comparison_run_id' => $runB->id,
        'field' => 'price',
        'old_value' => 'CHANGES_FILTER_SRC_B_MARKER',
        'new_value' => '2',
        'changed_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('changes.index', ['source' => $sourceA->id]))
        ->assertOk()
        ->assertSeeText('CHANGES_FILTER_SRC_A_MARKER')
        ->assertDontSeeText('CHANGES_FILTER_SRC_B_MARKER');
});

it('filters changes by field via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-filter-field',
        'name' => 'Changes Filter Field Source',
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

    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 1,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_FILTER_FIELD_PRICE',
        'new_value' => '1',
        'changed_at' => now()->subHour(),
    ]);
    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 1,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'status',
        'old_value' => 'CHANGES_FILTER_FIELD_STATUS',
        'new_value' => '2',
        'changed_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('changes.index', ['field' => 'price']))
        ->assertOk()
        ->assertSeeText('CHANGES_FILTER_FIELD_PRICE')
        ->assertDontSeeText('CHANGES_FILTER_FIELD_STATUS');
});

it('filters changes by changed_at date range via GET query', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-filter-dates',
        'name' => 'Changes Filter Dates Source',
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

    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 1,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_FILTER_DATE_EARLY',
        'new_value' => '1',
        'changed_at' => Carbon\Carbon::parse('2026-05-01 12:00:00'),
    ]);
    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 2,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_FILTER_DATE_MID',
        'new_value' => '2',
        'changed_at' => Carbon\Carbon::parse('2026-05-10 12:00:00'),
    ]);
    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 3,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_FILTER_DATE_LATE',
        'new_value' => '3',
        'changed_at' => Carbon\Carbon::parse('2026-05-20 12:00:00'),
    ]);

    $this->actingAs($user)
        ->get(route('changes.index', [
            'date_from' => '2026-05-05',
            'date_to' => '2026-05-15',
        ]))
        ->assertOk()
        ->assertDontSeeText('CHANGES_FILTER_DATE_EARLY')
        ->assertSeeText('CHANGES_FILTER_DATE_MID')
        ->assertDontSeeText('CHANGES_FILTER_DATE_LATE');
});

it('retains selected change filters in the filter form and shows a clear link', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-filter-retain',
        'name' => 'Changes Retain Source',
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

    ChangeLog::create([
        'entity_type' => 'plot',
        'entity_id' => 1,
        'dataset_comparison_run_id' => $run->id,
        'field' => 'price',
        'old_value' => 'CHANGES_RETAIN_BODY',
        'new_value' => '1',
        'changed_at' => Carbon\Carbon::parse('2026-05-10 10:00:00'),
    ]);

    $html = $this->actingAs($user)
        ->get(route('changes.index', [
            'source' => $source->id,
            'field' => 'price',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
        ]))
        ->assertOk()
        ->assertSee('href="'.route('changes.index').'"', false)
        ->assertSeeText('Clear filters')
        ->getContent();

    expect($html)->toMatch('/<option value="'.$source->id.'"[^>]*\bselected\b/');
    expect($html)->toMatch('/<option value="price"[^>]*\bselected\b/');
    expect($html)->toContain('name="date_from" value="2026-05-01"');
    expect($html)->toContain('name="date_to" value="2026-05-31"');
});

it('redirects unauthenticated users from filtered /changes to /login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:changes-filter-auth',
        'name' => 'Changes Auth Source',
    ]);

    $this->get(route('changes.index', ['source' => $source->id]))
        ->assertRedirect(route('login'));
});

it('shows change count summary with visible range on the first page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-count-summary',
        'name' => 'Changes Count Summary Source',
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

    for ($i = 0; $i < 101; $i++) {
        $change = ChangeLog::create([
            'entity_type' => 'plot',
            'entity_id' => $i,
            'dataset_comparison_run_id' => $run->id,
            'field' => 'price',
            'old_value' => 'CHANGES_COUNT_SUMMARY_'.$i,
            'new_value' => (string) $i,
            'changed_at' => now()->subMinutes($i),
        ]);
        $change->forceFill([
            'changed_at' => now()->subMinutes($i),
        ])->save();
    }

    $this->actingAs($user)
        ->get(route('changes.index', ['source' => $source->id]))
        ->assertOk()
        ->assertSee('data-test="changes-result-summary"', false)
        ->assertSeeText('101 changes match the current filters.')
        ->assertSeeText('Showing 1 to 100.')
        ->assertSee('data-test="changes-pagination"', false)
        ->assertSee('aria-current="page">1<', false)
        ->assertSeeText('CHANGES_COUNT_SUMMARY_0')
        ->assertSeeText('CHANGES_COUNT_SUMMARY_99')
        ->assertDontSeeText('CHANGES_COUNT_SUMMARY_100');
});

it('paginates changes beyond the first page while preserving newest-first order', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-pagination',
        'name' => 'Changes Pagination Source',
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

    for ($i = 0; $i < 101; $i++) {
        $change = ChangeLog::create([
            'entity_type' => 'plot',
            'entity_id' => $i,
            'dataset_comparison_run_id' => $run->id,
            'field' => 'price',
            'old_value' => 'CHANGES_PAGINATION_'.$i,
            'new_value' => (string) $i,
            'changed_at' => now()->subMinutes($i),
        ]);
        $change->forceFill([
            'changed_at' => now()->subMinutes($i),
        ])->save();
    }

    $this->actingAs($user)
        ->get(route('changes.index', [
            'source' => $source->id,
            'page' => 2,
        ]))
        ->assertOk()
        ->assertSeeText('Showing 101 to 101.')
        ->assertSeeText('CHANGES_PAGINATION_100')
        ->assertDontSeeText('CHANGES_PAGINATION_0')
        ->assertSee('data-test="changes-pagination"', false)
        ->assertSee('aria-current="page">2<', false)
        ->assertSee('cc-pagination__link--active', false);
});

it('shows numbered pagination links when there are multiple pages', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-numbered-pagination',
        'name' => 'Changes Numbered Pagination Source',
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

    for ($i = 0; $i < 101; $i++) {
        $change = ChangeLog::create([
            'entity_type' => 'plot',
            'entity_id' => $i,
            'dataset_comparison_run_id' => $run->id,
            'field' => 'price',
            'old_value' => 'CHANGES_NUMBERED_PAGINATION_'.$i,
            'new_value' => (string) $i,
            'changed_at' => now()->subMinutes($i),
        ]);
        $change->forceFill([
            'changed_at' => now()->subMinutes($i),
        ])->save();
    }

    $html = $this->actingAs($user)
        ->get(route('changes.index', ['source' => $source->id]))
        ->assertOk()
        ->assertSee('data-test="changes-pagination"', false)
        ->assertSeeText('First')
        ->assertSeeText('Last')
        ->getContent();

    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=2[^"]*">2<\/a>/');
    expect($html)->toMatch('/aria-current="page">1<\//');
});

it('uses compact windowed numbered pagination when there are many pages', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-windowed-pagination',
        'name' => 'Changes Windowed Pagination Source',
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

    for ($i = 0; $i < 1001; $i++) {
        $change = ChangeLog::create([
            'entity_type' => 'plot',
            'entity_id' => $i,
            'dataset_comparison_run_id' => $run->id,
            'field' => 'price',
            'old_value' => 'CHANGES_WINDOWED_PAGINATION_'.$i,
            'new_value' => (string) $i,
            'changed_at' => now()->subMinutes($i),
        ]);
        $change->forceFill([
            'changed_at' => now()->subMinutes($i),
        ])->save();
    }

    $html = $this->actingAs($user)
        ->get(route('changes.index', [
            'source' => $source->id,
            'page' => 6,
        ]))
        ->assertOk()
        ->assertSee('data-test="changes-pagination"', false)
        ->assertSee('aria-current="page">6<', false)
        ->getContent();

    expect($html)->toContain('cc-pagination__ellipsis');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=1[^"]*">1<\/a>/');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=4[^"]*">4<\/a>/');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=8[^"]*">8<\/a>/');
    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*page=11[^"]*">11<\/a>/');
    expect($html)->not->toMatch('/<a class="cc-pagination__link" href="[^"]*page=3[^"]*">3<\/a>/');
    expect($html)->not->toMatch('/<a class="cc-pagination__link" href="[^"]*page=9[^"]*">9<\/a>/');
    expect(substr_count($html, 'class="cc-pagination__ellipsis muted"'))->toBe(2);
    expect(preg_match_all('/class="cc-pagination__link" href="[^"]*page=\d+[^"]*">\d+<\/a>/', $html))->toBe(6);
});

it('preserves change filters in pagination links', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:changes-pagination-filters',
        'name' => 'Changes Pagination Filters Source',
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

    for ($i = 0; $i < 101; $i++) {
        $change = ChangeLog::create([
            'entity_type' => 'plot',
            'entity_id' => $i,
            'dataset_comparison_run_id' => $run->id,
            'field' => 'price',
            'old_value' => 'CHANGES_PAGINATION_FILTERS_'.$i,
            'new_value' => (string) $i,
            'changed_at' => now()->subMinutes($i),
        ]);
        $change->forceFill([
            'changed_at' => now()->subMinutes($i),
        ])->save();
    }

    $html = $this->actingAs($user)
        ->get(route('changes.index', [
            'source' => $source->id,
            'field' => 'price',
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<a class="cc-pagination__link" href="[^"]*source='.$source->id.'[^"]*field=price[^"]*date_from=2026-01-01[^"]*date_to=2026-12-31[^"]*page=2[^"]*">2<\/a>/');
});
