<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\MonitoredSource;
use App\Core\Services\SourceRunFailedIssueService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('shows the run now form on the source detail page when endpoint_url is set', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-now-form',
        'name' => 'Run Now Form',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSee('data-test="source-run-now-form"', false)
        ->assertSee('action="'.route('sources.run-now', $source).'"', false)
        ->assertSeeText('Run now')
        ->assertSeeText('Fetch live plot data');
});

it('does not show the run now form when endpoint_url is missing', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-now-no-endpoint',
        'name' => 'Run Now No Endpoint',
        'endpoint_url' => null,
    ]);

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertDontSee('data-test="source-run-now-form"', false);
});

it('redirects unauthenticated users from run now to login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:run-now-auth',
        'name' => 'Run Now Auth',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $this->post(route('sources.run-now', $source))
        ->assertRedirect(route('login'));
});

it('creates a completed run when run now succeeds', function () {
    Http::fake([
        'https://example.test/plots' => Http::response([['id' => 1, 'price' => 100_000, 'status' => 'available']], 200),
    ]);

    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-now-success',
        'name' => 'Run Now Success',
        'endpoint_url' => 'https://example.test/plots',
    ]);

    $this->actingAs($user)
        ->post(route('sources.run-now', $source))
        ->assertRedirect(route('sources.show', $source))
        ->assertSessionHas('status');

    $run = DatasetComparisonRun::query()->where('source_id', $source->id)->firstOrFail();
    expect($run->status)->toBe('baseline');

    $this->actingAs($user)
        ->get(route('sources.show', $source))
        ->assertOk()
        ->assertSeeText("Run #{$run->id}")
        ->assertSeeText('status: baseline');
});

it('records a failed run when run now cannot fetch live data', function () {
    Http::fake([
        'https://example.test/plots-fail' => Http::response([], 500),
    ]);

    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-now-fail',
        'name' => 'Run Now Fail',
        'endpoint_url' => 'https://example.test/plots-fail',
    ]);

    $this->actingAs($user)
        ->post(route('sources.run-now', $source))
        ->assertRedirect(route('sources.show', $source))
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Run failed')
            && str_contains($status, 'status failed')
            && ! str_contains($status, 'HTTP request failed'));

    $failedRun = DatasetComparisonRun::query()->where('source_id', $source->id)->firstOrFail();
    expect($failedRun->status)->toBe('failed');

    $issue = DatasetIssue::query()->where('dataset_comparison_run_id', $failedRun->id)->firstOrFail();
    expect($issue->issue_type)->toBe(SourceRunFailedIssueService::ISSUE_TYPE)
        ->and($issue->context)->toHaveKey('exception_message');
});

it('returns not found when run now is posted for a source without endpoint_url', function () {
    $user = User::factory()->create();
    $source = MonitoredSource::create([
        'key' => 'hb:run-now-post-no-endpoint',
        'name' => 'Run Now Post No Endpoint',
        'endpoint_url' => null,
    ]);

    $this->actingAs($user)
        ->post(route('sources.run-now', $source))
        ->assertNotFound();

    expect(DatasetComparisonRun::query()->where('source_id', $source->id)->count())->toBe(0);
});
