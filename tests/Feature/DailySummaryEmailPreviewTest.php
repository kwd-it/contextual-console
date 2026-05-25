<?php

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

const DAILY_SUMMARY_PREVIEW_TEST_ENV = 'testing';

/**
 * @var bool
 */
$GLOBALS['daily_summary_preview_test_refreshed_application'] = false;

afterEach(function (): void {
    restoreDailySummaryPreviewTestApplicationState();
});

function withApplicationEnv(string $env): void
{
    app()['env'] = $env;
}

function refreshApplicationWithEnv(string $env): void
{
    $_ENV['APP_ENV'] = $env;
    $_SERVER['APP_ENV'] = $env;
    putenv("APP_ENV={$env}");

    $GLOBALS['daily_summary_preview_test_refreshed_application'] = true;

    test()->refreshApplication();
    test()->artisan('migrate', ['--force' => true]);
}

function restoreDailySummaryPreviewTestApplicationState(): void
{
    $_ENV['APP_ENV'] = DAILY_SUMMARY_PREVIEW_TEST_ENV;
    $_SERVER['APP_ENV'] = DAILY_SUMMARY_PREVIEW_TEST_ENV;
    putenv('APP_ENV='.DAILY_SUMMARY_PREVIEW_TEST_ENV);

    if (($GLOBALS['daily_summary_preview_test_refreshed_application'] ?? false) === true) {
        $GLOBALS['daily_summary_preview_test_refreshed_application'] = false;
        test()->refreshApplication();

        return;
    }

    app()['env'] = DAILY_SUMMARY_PREVIEW_TEST_ENV;
}

it('renders the daily summary html email preview when environment is local', function () {
    withApplicationEnv('local');

    expect(Route::has('dev.daily-summary-email-preview'))->toBeTrue();

    $source = MonitoredSource::create(['key' => 'hb:preview', 'name' => 'Preview Source']);
    $snapshot = DatasetSnapshot::create(['source_id' => $source->id, 'payload' => []]);
    DatasetComparisonRun::create([
        'source_id' => $source->id,
        'current_snapshot_id' => $snapshot->id,
        'previous_snapshot_id' => null,
        'status' => 'completed',
        'summary' => ['added' => 1, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
        'started_at' => now()->subHour(),
        'finished_at' => now()->subHour()->addMinute(),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dev/daily-summary-email-preview')
        ->assertOk()
        ->assertSee('Contextual Console', false)
        ->assertSee('Daily monitoring summary', false)
        ->assertSee('Preview Source', false)
        ->assertSee('<!DOCTYPE html>', false);
});

it('redirects unauthenticated users from the preview route to login when environment is local', function () {
    withApplicationEnv('local');

    $this->get('/dev/daily-summary-email-preview')
        ->assertRedirect(route('login'));
});

it('returns not found for the preview route when runtime environment is production', function () {
    withApplicationEnv('production');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dev/daily-summary-email-preview')
        ->assertNotFound();
});

it('does not register the daily summary email preview route when application boots in production', function () {
    try {
        refreshApplicationWithEnv('production');

        expect(Route::has('dev.daily-summary-email-preview'))->toBeFalse();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dev/daily-summary-email-preview')
            ->assertNotFound();
    } finally {
        restoreDailySummaryPreviewTestApplicationState();
    }
});
