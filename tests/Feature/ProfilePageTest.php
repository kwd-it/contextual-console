<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

const PROFILE_PAGE_TEST_ENV = 'testing';

/**
 * @var bool
 */
$GLOBALS['profile_page_test_refreshed_application'] = false;

afterEach(function (): void {
    restoreProfilePageTestApplicationState();
});

function refreshProfilePageTestApplicationWithEnv(string $env): void
{
    $_ENV['APP_ENV'] = $env;
    $_SERVER['APP_ENV'] = $env;
    putenv("APP_ENV={$env}");

    $GLOBALS['profile_page_test_refreshed_application'] = true;

    test()->refreshApplication();
    test()->artisan('migrate', ['--force' => true]);
}

function restoreProfilePageTestApplicationState(): void
{
    $_ENV['APP_ENV'] = PROFILE_PAGE_TEST_ENV;
    $_SERVER['APP_ENV'] = PROFILE_PAGE_TEST_ENV;
    putenv('APP_ENV='.PROFILE_PAGE_TEST_ENV);

    if (($GLOBALS['profile_page_test_refreshed_application'] ?? false) === true) {
        $GLOBALS['profile_page_test_refreshed_application'] = false;
        test()->refreshApplication();

        return;
    }

    app()['env'] = PROFILE_PAGE_TEST_ENV;
}

it('redirects unauthenticated users from the profile page to login', function () {
    $this->get(route('profile.edit'))
        ->assertRedirect(route('login'));
});

it('allows authenticated users to view the profile page with account and email preferences', function () {
    $user = User::factory()->create([
        'name' => 'Alex Operator',
        'email' => 'alex@example.test',
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeText('Profile')
        ->assertSeeText('Personal account settings for the signed-in user.')
        ->assertSeeText('Signed in as')
        ->assertSeeText('Name')
        ->assertSeeText('Login email')
        ->assertSeeText('Alex Operator')
        ->assertSeeText('alex@example.test')
        ->assertSee('data-test="profile-user-name"', false)
        ->assertSee('data-test="profile-user-email"', false)
        ->assertSee('data-test="profile-account-summary"', false)
        ->assertSeeText('Daily summary email')
        ->assertSeeText('When enabled, the daily monitoring summary is sent to your login email address shown above.')
        ->assertSeeText('Send me the daily monitoring summary email')
        ->assertDontSee('Summary email address', false)
        ->assertDontSee('data-test="daily-summary-email"', false)
        ->assertSee('data-test="profile-form"', false)
        ->assertSee('href="'.route('profile.edit').'"', false);
});

it('shows the profile link in the dashboard navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('data-test="nav-profile"', false)
        ->assertSee('href="'.route('profile.edit').'"', false)
        ->assertSeeText('Profile');
});

it('allows users to enable daily summary emails without a separate email address', function () {
    $user = User::factory()->create([
        'email' => 'reports@example.test',
        'daily_summary_enabled' => false,
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'daily_summary_enabled' => '1',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status');

    $user->refresh();

    expect($user->daily_summary_enabled)->toBeTrue();
});

it('allows users to disable daily summary emails', function () {
    $user = User::factory()->create([
        'daily_summary_enabled' => true,
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->daily_summary_enabled)->toBeFalse();
});

it('shows the preview daily summary email link when the preview route is available', function () {
    expect(Route::has('dev.daily-summary-email-preview'))->toBeTrue();

    $previewHref = route('dev.daily-summary-email-preview');

    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="profile-preview-daily-summary"', false)
        ->assertSee('href="'.$previewHref.'"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSeeText('Preview daily summary email');
});

it('does not show the preview daily summary email link in production', function () {
    try {
        refreshProfilePageTestApplicationWithEnv('production');

        expect(Route::has('dev.daily-summary-email-preview'))->toBeFalse();

        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('data-test="profile-preview-daily-summary"', false)
            ->assertDontSee('Preview daily summary email', false);
    } finally {
        restoreProfilePageTestApplicationState();
    }
});
