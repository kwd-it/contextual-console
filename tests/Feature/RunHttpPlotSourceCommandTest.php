<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('fails with a useful message when the source key is unknown', function () {
    Http::fake();

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => 'hb:does-not-exist',
    ])->expectsOutputToContain('Monitored source not found')
        ->assertExitCode(1);
});

it('fails clearly when the source is missing endpoint_url', function () {
    Http::fake();

    $source = MonitoredSource::create([
        'key' => 'hb:http-missing-endpoint',
        'name' => 'HTTP Missing Endpoint',
        'endpoint_url' => null,
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->expectsOutputToContain('missing endpoint_url')
        ->assertExitCode(1);
});

it('runs a successful baseline run from an HTTP payload', function () {
    Http::fake([
        'https://example.test/plots' => Http::response([['id' => 1, 'price' => 100_000, 'status' => 'available']], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-baseline',
        'name' => 'HTTP Baseline',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->expectsOutputToContain("source: {$source->key}")
        ->expectsOutputToContain('status: baseline')
        ->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);

    expect(DatasetSnapshot::query()->where('source_id', $source->id)->count())->toBe(1);
    $run = DatasetComparisonRun::query()->where('source_id', $source->id)->latest('id')->firstOrFail();
    expect($run->status)->toBe('baseline');
});

it('runs a successful completed run from an HTTP payload after a baseline exists', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:http-completed',
        'name' => 'HTTP Completed',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    Http::fake([
        'https://example.test/plots' => Http::response([
            ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ], 200),
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->assertExitCode(0);

    Http::fake([
        'https://example.test/plots' => Http::response([
            ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
            ['id' => 2, 'price' => 200_000, 'status' => 'available'],
        ], 200),
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->expectsOutputToContain('status: completed')
        ->expectsOutputToContain('summary: added=')
        ->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);

    expect(DatasetSnapshot::query()->where('source_id', $source->id)->count())->toBe(2);
    $run2 = DatasetComparisonRun::query()->where('source_id', $source->id)->latest('id')->firstOrFail();
    expect($run2->status)->toBe('completed');
});

it('returns failure when the remote payload is invalid', function () {
    Http::fake([
        'https://example.test/plots' => Http::response(['key' => 'value'], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-invalid-remote-payload',
        'name' => 'HTTP Invalid Remote Payload',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->expectsOutputToContain('expected a JSON array')
        ->assertExitCode(1);

    expect(DatasetComparisonRun::count())->toBe(0);
    expect(DatasetSnapshot::count())->toBe(0);
});

it('ingests a contextualwp-style wrapped list after unwrap and field mapping', function () {
    Http::fake([
        'https://example.test/wp-json/mcp/v1/list_contexts' => Http::response([
            'contexts' => [
                ['post_id' => 7, 'acf' => ['price' => 300_000, 'status' => 'sold']],
            ],
        ], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-contextualwp-shape',
        'name' => 'HTTP ContextualWP shape',
        'endpoint_url' => 'https://example.test/wp-json/mcp/v1/list_contexts',
        'http_plot_payload_adapter' => 'contextualwp_list_contexts',
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);

    $snapshot = DatasetSnapshot::query()->where('source_id', $source->id)->latest('id')->firstOrFail();
    $payload = $snapshot->payload;
    expect($payload)->toBeArray()
        ->and($payload[0])->toMatchArray([
            'id' => 7,
            'price' => 300_000,
            'status' => 'sold',
        ]);
});

it('prints issue counts (and severity breakdown) when remote payload contains invalid plot data', function () {
    Http::fake([
        'https://example.test/plots' => Http::response([
            'bad-record',
            ['price' => 100_000, 'status' => 'available'], // missing id
            ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-invalid-data',
        'name' => 'HTTP Invalid Data',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $this->artisan('contextual-console:run-http-plot-source', [
        'sourceKey' => $source->key,
    ])->expectsOutputToContain('Issues: 2')
        ->expectsOutputToContain('- error: 2')
        ->assertExitCode(0);
});
