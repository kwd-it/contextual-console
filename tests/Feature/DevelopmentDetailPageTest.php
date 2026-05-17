<?php

use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotDatasetRunService;
use App\Models\User;
use App\Support\DevelopmentRouteSlug;
use App\Support\PlotDevelopmentLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from the development detail page to login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-auth',
        'name' => 'Dev Detail Auth Source',
    ]);

    $slug = DevelopmentRouteSlug::encode('Alpha Fields');

    $this->get(route('sources.developments.show', [$source, $slug]))
        ->assertRedirect(route('login'));
});

it('allows authenticated users to view a development detail page with plots for that development only', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-plots',
        'name' => 'Dev Detail Plots Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available', 'development' => 'Alpha Fields'],
    ]);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Plot One',
            'price' => 110_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
            'bedrooms' => 3,
            'house_type' => 'Detached',
            'url' => 'https://example.test/plot-1',
        ],
        [
            'id' => 2,
            'title' => 'Plot Two',
            'price' => 220_000,
            'status' => 'reserved',
            'development' => 'Beta Meadows',
        ],
    ]);

    $slug = DevelopmentRouteSlug::encode('Alpha Fields');

    $html = $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSeeText('Alpha Fields')
        ->assertSeeText('Dev Detail Plots Source')
        ->assertSeeText('Plot One')
        ->assertDontSeeText('Plot Two')
        ->assertDontSeeText('Beta Meadows')
        ->assertSeeText('110000')
        ->assertSeeText('Detached')
        ->assertSee('https://example.test/plot-1', false)
        ->assertSee('data-test="development-plots-table"', false)
        ->assertSee('data-test="development-plot-row"', false)
        ->assertSee('data-test="development-back-dashboard"', false)
        ->assertSee('data-test="development-back-source"', false)
        ->getContent();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $rowNodes = $xpath->query("//*[@data-test='development-plot-row']");
    expect($rowNodes)->not->toBeFalse();
    expect($rowNodes->length)->toBe(1);

    $technicalId = trim((string) $xpath->evaluate(
        "string(.//*[@data-test='development-plot-technical-id'])",
        $rowNodes->item(0),
    ));
    expect($technicalId)->toBe('1');
});

it('links dashboard development names to the development detail page', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-dash-link',
        'name' => 'Dev Detail Dashboard Link Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available', 'development' => 'Alpha Fields'],
    ]);
    $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'available', 'development' => 'Alpha Fields'],
    ]);

    $developmentHref = route('sources.developments.show', [
        $source,
        DevelopmentRouteSlug::encode('Alpha Fields'),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('href="'.$developmentHref.'"', false)
        ->assertSee('data-test="dashboard-development-overview-development-link"', false);
});

it('handles unknown development drilldown safely', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-unknown',
        'name' => 'Dev Detail Unknown Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $service->run($source, [
        ['id' => 1, 'price' => 110_000, 'status' => 'available', 'development' => ''],
        ['id' => 2, 'price' => 120_000, 'status' => 'reserved', 'development' => 'Known Site'],
    ]);

    $unknownSlug = DevelopmentRouteSlug::encode(PlotDevelopmentLabel::UNKNOWN_LABEL);
    $unknownHref = route('sources.developments.show', [$source, $unknownSlug]);

    $html = $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('href="'.$unknownHref.'"', false)
        ->getContent();

    $this->actingAs($user)
        ->get($unknownHref)
        ->assertOk()
        ->assertSeeText('Unknown development')
        ->assertSee('data-test="development-plots-table"', false)
        ->assertSee('data-test="development-plot-row"', false);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $unknownLink = $xpath->query(
        "//*[@data-test='dashboard-development-overview-development-link' and contains(normalize-space(.), 'Unknown development')]",
    );
    expect($unknownLink)->not->toBeFalse();
    expect($unknownLink->length)->toBe(1);
});

it('shows an empty state when the development is not in the latest snapshot', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-not-found',
        'name' => 'Dev Detail Not Found Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available', 'development' => 'Alpha Fields'],
    ]);

    $slug = DevelopmentRouteSlug::encode('Missing Development');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSee('data-test="development-empty-not-found"', false)
        ->assertSeeText('Development not found');
});

it('groups dashboard development overview using URL-derived labels when development fields are blank', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-url-fallback-dash',
        'name' => 'Dev Detail URL Fallback Dashboard Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Plot 241',
            'price' => 110_000,
            'status' => 'available',
            'development' => '',
            'url' => 'https://www.wyatthomes.co.uk/developments/brimsmore-townhouse-collection/plot-241/',
        ],
    ]);

    $fallbackLabel = 'Brimsmore Townhouse Collection';
    $developmentHref = route('sources.developments.show', [
        $source,
        DevelopmentRouteSlug::encode($fallbackLabel),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('href="'.$developmentHref.'"', false)
        ->assertSeeText($fallbackLabel)
        ->assertSee('data-test="dashboard-development-overview-development-link"', false);
});

it('lists development detail plots matched by URL-derived fallback labels', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-url-fallback-show',
        'name' => 'Dev Detail URL Fallback Show Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Plot 241',
            'price' => 110_000,
            'status' => 'available',
            'development' => '',
            'url' => 'https://www.wyatthomes.co.uk/developments/brimsmore-townhouse-collection/plot-241/',
        ],
        [
            'id' => 2,
            'title' => 'Other Plot',
            'price' => 220_000,
            'status' => 'reserved',
            'development' => 'Known Site',
        ],
    ]);

    $slug = DevelopmentRouteSlug::encode('Brimsmore Townhouse Collection');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSeeText('Brimsmore Townhouse Collection')
        ->assertSeeText('Plot 241')
        ->assertDontSeeText('Other Plot')
        ->assertDontSeeText('Known Site')
        ->assertSee('data-test="development-plot-row"', false);
});

it('shows recent changes for plots in the selected development only', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-recent-changes',
        'name' => 'Dev Detail Recent Changes Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Alpha Plot',
            'price' => 100_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
        [
            'id' => 2,
            'title' => 'Beta Plot',
            'price' => 200_000,
            'status' => 'available',
            'development' => 'Beta Meadows',
        ],
    ]);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Alpha Plot',
            'price' => 110_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
        [
            'id' => 2,
            'title' => 'Beta Plot',
            'price' => 250_000,
            'status' => 'available',
            'development' => 'Beta Meadows',
        ],
    ]);

    $slug = DevelopmentRouteSlug::encode('Alpha Fields');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSee('data-test="development-recent-changes-table"', false)
        ->assertSee('data-test="development-recent-change-row"', false)
        ->assertSeeText('Alpha Plot')
        ->assertSeeText('100000')
        ->assertSeeText('110000')
        ->assertSeeText('price')
        ->assertDontSeeText('250000')
        ->assertDontSeeText('Beta Plot');
});

it('shows recent issues for plots in the selected development only', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-recent-issues',
        'name' => 'Dev Detail Recent Issues Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Alpha Plot',
            'price' => 100_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
        [
            'id' => 2,
            'title' => 'Beta Plot',
            'price' => 200_000,
            'status' => 'available',
            'development' => 'Beta Meadows',
        ],
    ]);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Alpha Plot',
            'price' => 100_000,
            'status' => 'reserved',
            'development' => 'Alpha Fields',
        ],
        [
            'id' => 2,
            'title' => 'Beta Plot',
            'price' => 200_000,
            'status' => 'sold',
            'development' => 'Beta Meadows',
        ],
    ]);

    $slug = DevelopmentRouteSlug::encode('Alpha Fields');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSee('data-test="development-recent-issues-table"', false)
        ->assertSee('data-test="development-recent-issue-row"', false)
        ->assertSeeText('Alpha Plot')
        ->assertSeeText('status')
        ->assertDontSeeText('Beta Plot')
        ->assertDontSeeText('sold');
});

it('does not show recent changes or issues from other monitored sources', function () {
    $user = User::factory()->create();
    $sourceA = MonitoredSource::create([
        'key' => 'hb:dev-detail-source-a',
        'name' => 'Dev Detail Source A',
    ]);
    $sourceB = MonitoredSource::create([
        'key' => 'hb:dev-detail-source-b',
        'name' => 'Dev Detail Source B',
    ]);

    $service = app(PlotDatasetRunService::class);

    $service->run($sourceA, [
        [
            'id' => 1,
            'title' => 'Shared Name Plot',
            'price' => 100_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
    ]);
    $service->run($sourceA, [
        [
            'id' => 1,
            'title' => 'Shared Name Plot',
            'price' => 110_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
    ]);

    $service->run($sourceB, [
        [
            'id' => 1,
            'title' => 'Shared Name Plot',
            'price' => 100_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
    ]);
    $service->run($sourceB, [
        [
            'id' => 1,
            'title' => 'Shared Name Plot',
            'price' => 999_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
    ]);

    $slug = DevelopmentRouteSlug::encode('Alpha Fields');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$sourceA, $slug]))
        ->assertOk()
        ->assertSeeText('110000')
        ->assertDontSeeText('999000');
});

it('shows empty states when there are no matching recent changes or issues', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-recent-empty',
        'name' => 'Dev Detail Recent Empty Source',
    ]);

    $service = app(PlotDatasetRunService::class);
    $service->run($source, [
        [
            'id' => 1,
            'title' => 'Alpha Plot',
            'price' => 100_000,
            'status' => 'available',
            'development' => 'Alpha Fields',
        ],
    ]);

    $slug = DevelopmentRouteSlug::encode('Alpha Fields');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSee('data-test="development-empty-no-recent-changes"', false)
        ->assertSee('data-test="development-empty-no-recent-issues"', false)
        ->assertSeeText('No recent changes for this development')
        ->assertSeeText('No recent issues for this development');
});

it('shows an empty state when the source has no snapshot for development drilldown', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:dev-detail-no-snapshot',
        'name' => 'Dev Detail No Snapshot Source',
    ]);

    $slug = DevelopmentRouteSlug::encode('Any Development');

    $this->actingAs($user)
        ->get(route('sources.developments.show', [$source, $slug]))
        ->assertOk()
        ->assertSee('data-test="development-empty-no-snapshot"', false)
        ->assertSeeText('No snapshot data for this source');
});
