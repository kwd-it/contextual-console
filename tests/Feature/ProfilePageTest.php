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

it('allows authenticated users to view the email preferences profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeText('Email reports')
        ->assertSeeText('Daily summary email')
        ->assertSee('data-test="profile-form"', false)
        ->assertSee('href="'.route('profile.edit').'"', false);
});

it('shows the email reports link in the dashboard navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('data-test="nav-profile"', false)
        ->assertSee('href="'.route('profile.edit').'"', false)
        ->assertSeeText('Email reports');
});

it('allows users to enable daily summary emails with a valid email address', function () {
    $user = User::factory()->create([
        'daily_summary_enabled' => false,
        'daily_summary_email' => null,
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'daily_summary_enabled' => '1',
            'daily_summary_email' => '  reports@example.test  ',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status');

    $user->refresh();

    expect($user->daily_summary_enabled)->toBeTrue();
    expect($user->daily_summary_email)->toBe('reports@example.test');
});

it('allows users to disable daily summary emails', function () {
    $user = User::factory()->create([
        'daily_summary_enabled' => true,
        'daily_summary_email' => 'reports@example.test',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'daily_summary_email' => 'reports@example.test',
        ])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->daily_summary_enabled)->toBeFalse();
    expect($user->daily_summary_email)->toBeNull();
});

it('rejects invalid email when enabling daily summary emails', function () {
    $user = User::factory()->create([
        'daily_summary_enabled' => false,
        'daily_summary_email' => null,
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.update'), [
            'daily_summary_enabled' => '1',
            'daily_summary_email' => 'not-an-email',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('daily_summary_email');

    $user->refresh();

    expect($user->daily_summary_enabled)->toBeFalse();
    expect($user->daily_summary_email)->toBeNull();
});

it('rejects enabling daily summary emails without an email address', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.update'), [
            'daily_summary_enabled' => '1',
            'daily_summary_email' => '',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('daily_summary_email');
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
