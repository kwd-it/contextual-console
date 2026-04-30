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

it('sends a full Authorization header value from env when configured', function () {
    $_ENV['CC_TEST_BASIC_AUTH'] = 'Basic dGVzdDp0b2tlbg==';
    putenv('CC_TEST_BASIC_AUTH=Basic dGVzdDp0b2tlbg==');

    Http::fake(function ($request) {
        expect($request->header('Authorization'))->toBe(['Basic dGVzdDp0b2tlbg==']);

        return Http::response([['id' => 1]], 200);
    });

    $source = MonitoredSource::create([
        'key' => 'hb:http-auth-basic',
        'name' => 'HTTP Auth Basic',
        'endpoint_url' => 'https://example.test/plots',
        'auth_header_name' => 'Authorization',
        'auth_token_env_key' => 'CC_TEST_BASIC_AUTH',
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
        ->toThrow(RuntimeException::class, 'Missing required auth header env value');
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
        ->toThrow(RuntimeException::class, 'expected a JSON array at the top level');
});

it('unwraps a JSON object list using http_json_items_key', function () {
    Http::fake([
        'https://example.test/plots' => Http::response([
            'items' => [['id' => 1, 'price' => 1, 'status' => 'available']],
        ], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-wrapped',
        'name' => 'HTTP Wrapped',
        'endpoint_url' => 'https://example.test/plots',
        'http_json_items_key' => 'items',
    ]);

    $payload = app(HttpJsonSourceFetcher::class)->fetch($source);

    expect($payload)->toBe([['id' => 1, 'price' => 1, 'status' => 'available']]);
});

it('defaults to the contexts key when contextualwp_list_contexts adapter is set and items key is empty', function () {
    Http::fake([
        'https://example.test/mcp' => Http::response([
            'contexts' => [['id' => 5]],
        ], 200),
    ]);

    $source = MonitoredSource::create([
        'key' => 'hb:http-contextualwp-default-key',
        'name' => 'HTTP ContextualWP default key',
        'endpoint_url' => 'https://example.test/mcp',
        'http_plot_payload_adapter' => 'contextualwp_list_contexts',
    ]);

    $payload = app(HttpJsonSourceFetcher::class)->fetch($source);

    expect($payload)->toBe([['id' => 5]]);
});

it('fetches all pages when page_per_page pagination mode is enabled and merges results', function () {
    $requests = [];

    Http::fake(function ($request) use (&$requests) {
        $requests[] = $request->url();

        $parts = parse_url($request->url());
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        $page = (int) ($query['page'] ?? 0);
        $perPage = (int) ($query['per_page'] ?? 0);

        expect($perPage)->toBe(2);

        return match ($page) {
            1 => Http::response([['id' => 1], ['id' => 2]], 200), // full page => continue
            2 => Http::response([['id' => 3]], 200), // partial page => stop
            default => Http::response([['id' => 999]], 200),
        };
    });

    $source = MonitoredSource::create([
        'key' => 'hb:http-paginated',
        'name' => 'HTTP Paginated',
        'endpoint_url' => 'https://example.test/plots',
        'http_pagination_mode' => 'page_per_page',
        'http_page_param' => 'page',
        'http_per_page_param' => 'per_page',
        'http_per_page' => 2,
        'http_max_pages' => 10,
    ]);

    $payload = app(HttpJsonSourceFetcher::class)->fetch($source);

    expect($payload)->toBe([['id' => 1], ['id' => 2], ['id' => 3]])
        ->and(count($requests))->toBe(2);
});

it('preserves existing query params when adding pagination params', function () {
    Http::fake(function ($request) {
        $parts = parse_url($request->url());
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        expect($query)->toMatchArray([
            'foo' => 'bar',
            'page' => '1',
            'per_page' => '2',
        ]);

        return Http::response([['id' => 1]], 200);
    });

    $source = MonitoredSource::create([
        'key' => 'hb:http-paginated-with-query',
        'name' => 'HTTP Paginated With Query',
        'endpoint_url' => 'https://example.test/plots?foo=bar',
        'http_pagination_mode' => 'page_per_page',
        'http_per_page' => 2,
        'http_max_pages' => 1,
    ]);

    $payload = app(HttpJsonSourceFetcher::class)->fetch($source);

    expect($payload)->toBe([['id' => 1]]);
});
