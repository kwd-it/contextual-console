<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
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

it('records a failed run and issue when a scheduled source fails, and continues running other sources', function () {
    Http::fake([
        'https://example.test/plots-fail' => Http::response([], 500),
        'https://example.test/plots-ok' => Http::response([['id' => 2, 'price' => 200_000, 'status' => 'available']], 200),
    ]);

    $fail = MonitoredSource::create([
        'key' => 'hb:http-fail',
        'name' => 'HTTP Fail',
        'endpoint_url' => 'https://example.test/plots-fail',
    ]);

    $ok = MonitoredSource::create([
        'key' => 'hb:http-ok',
        'name' => 'HTTP OK',
        'endpoint_url' => 'https://example.test/plots-ok',
    ]);

    $this->artisan('contextual-console:run-scheduled-sources')
        ->expectsOutputToContain('Running 2 monitored HTTP plot source(s)...')
        ->expectsOutputToContain("source={$fail->key}")
        ->expectsOutputToContain("source={$ok->key}")
        ->assertExitCode(1);

    expect(DatasetComparisonRun::query()->where('source_id', $fail->id)->count())->toBe(1)
        ->and(DatasetComparisonRun::query()->where('source_id', $ok->id)->count())->toBe(1);

    $failedRun = DatasetComparisonRun::query()->where('source_id', $fail->id)->firstOrFail();
    expect($failedRun->status)->toBe('failed')
        ->and($failedRun->current_snapshot_id)->toBeNull()
        ->and($failedRun->summary)->toBeNull()
        ->and($failedRun->started_at)->not->toBeNull()
        ->and($failedRun->finished_at)->not->toBeNull();

    $issue = DatasetIssue::query()->where('dataset_comparison_run_id', $failedRun->id)->firstOrFail();
    expect($issue->severity)->toBe('error')
        ->and($issue->issue_type)->toBe('source_run_failed')
        ->and($issue->monitored_source_id)->toBe($fail->id)
        ->and($issue->dataset_snapshot_id)->toBeNull()
        ->and($issue->context)->toMatchArray([
            'exception_message' => $issue->context['exception_message'] ?? null,
        ])
        ->and((string) ($issue->context['exception_message'] ?? ''))->toContain('HTTP request failed');

    $okRun = DatasetComparisonRun::query()->where('source_id', $ok->id)->firstOrFail();
    expect($okRun->status)->not->toBe('failed')
        ->and($okRun->current_snapshot_id)->not->toBeNull();
});
