<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\MonitoredSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('runs sources that have endpoint_url set', function () {
    Http::fake([
        'https://example.test/plots-a' => Http::response([['id' => 1, 'price' => 100_000, 'status' => 'available']], 200),
        'https://example.test/plots-b' => Http::response([['id' => 2, 'price' => 200_000, 'status' => 'available']], 200),
    ]);

    $a = MonitoredSource::create([
        'key' => 'hb:http-a',
        'name' => 'HTTP A',
        'endpoint_url' => 'https://example.test/plots-a',
    ]);

    $b = MonitoredSource::create([
        'key' => 'hb:http-b',
        'name' => 'HTTP B',
        'endpoint_url' => 'https://example.test/plots-b',
    ]);

    $this->artisan('contextual-console:run-scheduled-sources')
        ->expectsOutputToContain('Running 2 monitored HTTP plot source(s)...')
        ->expectsOutputToContain("source={$a->key}")
        ->expectsOutputToContain("source={$b->key}")
        ->assertExitCode(0);

    expect(DatasetComparisonRun::query()->where('source_id', $a->id)->count())->toBe(1)
        ->and(DatasetComparisonRun::query()->where('source_id', $b->id)->count())->toBe(1);
});

it('skips sources without endpoint_url', function () {
    Http::fake([
        'https://example.test/plots-only' => Http::response([['id' => 1, 'price' => 100_000, 'status' => 'available']], 200),
    ]);

    $eligible = MonitoredSource::create([
        'key' => 'hb:http-eligible',
        'name' => 'Eligible',
        'endpoint_url' => 'https://example.test/plots-only',
    ]);

    $ineligible = MonitoredSource::create([
        'key' => 'hb:http-ineligible',
        'name' => 'Ineligible',
        'endpoint_url' => null,
    ]);

    $this->artisan('contextual-console:run-scheduled-sources')
        ->expectsOutputToContain("source={$eligible->key}")
        ->doesntExpectOutputToContain("source={$ineligible->key}")
        ->assertExitCode(0);

    expect(DatasetComparisonRun::query()->where('source_id', $eligible->id)->count())->toBe(1)
        ->and(DatasetComparisonRun::query()->where('source_id', $ineligible->id)->count())->toBe(0);
});

it('exits successfully when there are no eligible sources', function () {
    Http::fake();

    MonitoredSource::create([
        'key' => 'hb:no-endpoint',
        'name' => 'No Endpoint',
        'endpoint_url' => null,
    ]);

    $this->artisan('contextual-console:run-scheduled-sources')
        ->expectsOutputToContain('No eligible monitored HTTP plot sources found')
        ->assertExitCode(0);

    expect(DatasetComparisonRun::count())->toBe(0);
});
