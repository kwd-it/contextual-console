<?php

use App\Mail\ContextualConsoleDailySummaryMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::factory()->create(['daily_summary_enabled' => true]);
});

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
        ->assertSeeText('Account details')
        ->assertSeeText('Login email is managed by admins.')
        ->assertSeeText('Name')
        ->assertSeeText('Login email')
        ->assertSeeText('Password')
        ->assertSeeText('Current password')
        ->assertSeeText('New password')
        ->assertSeeText('Confirm new password')
        ->assertSee('data-test="profile-account-form"', false)
        ->assertSee('data-test="profile-name-input"', false)
        ->assertSee('data-test="profile-email-readonly"', false)
        ->assertSee('readonly', false)
        ->assertSee('value="Alex Operator"', false)
        ->assertSee('value="alex@example.test"', false)
        ->assertSee('data-test="profile-password-form"', false)
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

it('shows a fallback warning on the profile page when no users are subscribed', function () {
    User::query()->delete();
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');

    $this->actingAs(User::factory()->create(['daily_summary_enabled' => false]))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="daily-summary-subscription-warning"', false)
        ->assertSee('data-test-severity="fallback"', false)
        ->assertSeeText('Emails can still use the fallback recipient');
});

it('shows a stronger warning on the profile page when no users are subscribed and no fallback is configured', function () {
    User::query()->delete();
    config()->set('contextual_console.daily_summary_to', null);

    $this->actingAs(User::factory()->create(['daily_summary_enabled' => false]))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test-severity="none"', false)
        ->assertSee('cc-warning-banner--critical', false)
        ->assertSeeText('Scheduled summary emails will not be sent.');
});

it('does not show a daily summary subscription warning when at least one user is subscribed', function () {
    config()->set('contextual_console.daily_summary_to', null);

    $this->actingAs(User::factory()->create(['daily_summary_enabled' => false]))
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('data-test="daily-summary-subscription-warning"', false);
});

it('shows the send test daily summary email form on the profile page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="profile-daily-summary-test-email-form"', false)
        ->assertSee('data-test="profile-daily-summary-test-email"', false)
        ->assertSee('action="'.route('profile.daily-summary-test-email').'"', false)
        ->assertSeeText('Send test email')
        ->assertSeeText('Send the current daily summary to your login email address only.');
});

it('redirects unauthenticated users who post a daily summary test email to login', function () {
    Mail::fake();

    $this->post(route('profile.daily-summary-test-email'))
        ->assertRedirect(route('login'));

    Mail::assertNothingSent();
});

it('sends one daily summary test email to the logged-in user', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'tester@example.test']);

    $this->actingAs($user)
        ->post(route('profile.daily-summary-test-email'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('daily_summary_test_flash.type', 'success');

    Mail::assertSent(ContextualConsoleDailySummaryMail::class, 1);
    Mail::assertSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('tester@example.test'));
});

it('does not send a daily summary test email to the fallback recipient', function () {
    Mail::fake();
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');

    $user = User::factory()->create([
        'email' => 'tester@example.test',
        'daily_summary_enabled' => false,
    ]);

    $this->actingAs($user)
        ->post(route('profile.daily-summary-test-email'))
        ->assertRedirect(route('profile.edit'));

    Mail::assertSent(ContextualConsoleDailySummaryMail::class, 1);
    Mail::assertNotSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('ops@example.test'));
});

it('does not send a daily summary test email to other subscribed users', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'alice@example.test',
        'daily_summary_enabled' => true,
    ]);

    $user = User::factory()->create([
        'email' => 'tester@example.test',
        'daily_summary_enabled' => false,
    ]);

    $this->actingAs($user)
        ->post(route('profile.daily-summary-test-email'))
        ->assertRedirect(route('profile.edit'));

    Mail::assertSent(ContextualConsoleDailySummaryMail::class, 1);
    Mail::assertSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('tester@example.test'));
    Mail::assertNotSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('alice@example.test'));
});

it('sends a daily summary test email when daily summary is disabled for the user', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'tester@example.test',
        'daily_summary_enabled' => false,
    ]);

    $this->actingAs($user)
        ->post(route('profile.daily-summary-test-email'))
        ->assertRedirect(route('profile.edit'));

    Mail::assertSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('tester@example.test'));
});

it('shows a success flash after sending a daily summary test email', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'tester@example.test']);

    $this->actingAs($user)
        ->post(route('profile.daily-summary-test-email'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('daily_summary_test_flash', [
            'type' => 'success',
            'message' => 'Test email sent to your login address. Delivery can take a few minutes; check spam if it does not appear.',
        ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="profile-daily-summary-test-flash"', false)
        ->assertSee('data-test-flash-type="success"', false)
        ->assertSee('cc-flash--success', false);
});

it('allows authenticated users to update their own name', function () {
    $user = User::factory()->create([
        'name' => 'Alex Operator',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update-account'), [
            'name' => 'Alex Updated',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('account_status', 'Account details saved.');

    expect($user->fresh()->name)->toBe('Alex Updated');
});

it('does not allow authenticated users to update their own email from the profile page', function () {
    $user = User::factory()->create([
        'email' => 'alex@example.test',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update-account'), [
            'name' => $user->name,
            'email' => 'changed@example.test',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->email)->toBe('alex@example.test');
});

it('displays email as read-only on the profile page', function () {
    $user = User::factory()->create([
        'email' => 'readonly@example.test',
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-test="profile-email-readonly"', false)
        ->assertSee('readonly', false)
        ->assertSee('value="readonly@example.test"', false)
        ->assertDontSee('name="email"', false);
});

it('allows authenticated users to update their password with the correct current password', function () {
    $currentPassword = 'a-long-secure-password';
    $newPassword = 'another-long-secure-password';

    $user = User::factory()->create([
        'password' => $currentPassword,
    ]);

    $this->actingAs($user)
        ->put(route('profile.update-password'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('password_status', 'Password updated.');

    $this->assertTrue(password_verify($newPassword, $user->fresh()->password));
});

it('rejects password updates with an incorrect current password', function () {
    $user = User::factory()->create([
        'password' => 'a-long-secure-password',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update-password'), [
            'current_password' => 'wrong-current-password',
            'password' => 'another-long-secure-password',
            'password_confirmation' => 'another-long-secure-password',
        ])
        ->assertSessionHasErrors('current_password');

    $this->assertTrue(password_verify('a-long-secure-password', $user->fresh()->password));
});

it('validates password confirmation and minimum length on profile password update', function () {
    $user = User::factory()->create([
        'password' => 'a-long-secure-password',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update-password'), [
            'current_password' => 'a-long-secure-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->put(route('profile.update-password'), [
            'current_password' => 'a-long-secure-password',
            'password' => 'another-long-secure-password',
            'password_confirmation' => 'mismatched-long-secure-password',
        ])
        ->assertSessionHasErrors('password');
});

it('redirects unauthenticated users from profile account update to login', function () {
    $this->put(route('profile.update-account'), [
        'name' => 'Guest User',
    ])->assertRedirect(route('login'));
});

it('redirects unauthenticated users from profile password update to login', function () {
    $this->put(route('profile.update-password'), [
        'current_password' => 'a-long-secure-password',
        'password' => 'another-long-secure-password',
        'password_confirmation' => 'another-long-secure-password',
    ])->assertRedirect(route('login'));
});
