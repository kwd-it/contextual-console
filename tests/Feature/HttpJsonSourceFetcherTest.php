<?php

use App\Core\Models\MonitoredSource;
use App\Core\Services\HttpJsonSourceFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('fetches and decodes a top-level JSON array', function () {
    Http::fake([
        'https://example.test/plots' => Http::response([['id' => 1]], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-basic',
        'name' => 'HTTP Basic',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $payload = app(HttpJsonSourceFetcher::class)->fetch($source);

    expect($payload)->toBe([['id' => 1]]);
});

it('sends the configured auth header when auth settings are present', function () {
    $_ENV['CC_TEST_TOKEN'] = 'test-token';
    putenv('CC_TEST_TOKEN=test-token');

    Http::fake(function ($request) {
        expect($request->header('X-ContextualWP-Token'))->toBe(['test-token']);

        return Http::response([['id' => 1]], 200);
    });

    $source = MonitoredSource::create([
        'key' => 'hb:http-auth',
        'name' => 'HTTP Auth',
        'endpoint_url' => 'https://example.test/plots',
        'auth_header_name' => 'X-ContextualWP-Token',
        'auth_token_env_key' => 'CC_TEST_TOKEN',
    ]);

    $payload = app(HttpJsonSourceFetcher::class)->fetch($source);

    expect($payload)->toBe([['id' => 1]]);
});

it('fails clearly when endpoint_url is missing', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:http-missing-endpoint',
        'name' => 'HTTP Missing Endpoint',
        'endpoint_url' => null,
    ]);

    expect(fn () => app(HttpJsonSourceFetcher::class)->fetch($source))
        ->toThrow(RuntimeException::class, 'missing endpoint_url');
});

it('fails clearly when auth token env key is configured but no token is available', function () {
    unset($_ENV['CC_MISSING_TOKEN']);
    putenv('CC_MISSING_TOKEN');

    Http::fake([
        'https://example.test/plots' => Http::response([['id' => 1]], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-missing-token',
        'name' => 'HTTP Missing Token',
        'endpoint_url' => 'https://example.test/plots',
        'auth_header_name' => 'Authorization',
        'auth_token_env_key' => 'CC_MISSING_TOKEN',
    ]);

    expect(fn () => app(HttpJsonSourceFetcher::class)->fetch($source))
        ->toThrow(RuntimeException::class, 'Missing required auth token env value');
});

it('fails clearly on non-successful HTTP response', function () {
    Http::fake([
        'https://example.test/plots' => Http::response(['nope' => true], 500),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-500',
        'name' => 'HTTP 500',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    expect(fn () => app(HttpJsonSourceFetcher::class)->fetch($source))
        ->toThrow(RuntimeException::class, 'status 500');
});

it('fails clearly when the response is invalid JSON', function () {
    Http::fake([
        'https://example.test/plots' => Http::response('{"not valid json"', 200, ['Content-Type' => 'application/json']),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-invalid-json',
        'name' => 'HTTP Invalid JSON',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    expect(fn () => app(HttpJsonSourceFetcher::class)->fetch($source))
        ->toThrow(RuntimeException::class, 'Invalid JSON response');
});

it('fails clearly when JSON is not a top-level array', function () {
    Http::fake([
        'https://example.test/plots' => Http::response(['key' => 'value'], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-non-array',
        'name' => 'HTTP Non Array',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    expect(fn () => app(HttpJsonSourceFetcher::class)->fetch($source))
        ->toThrow(RuntimeException::class, 'expected a JSON array');
});
