<?php

use App\Mail\ContextualConsoleDailySummaryMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('allows admin users to view the users page', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.test',
    ]);

    User::factory()->create([
        'name' => 'Operator User',
        'email' => 'operator@example.test',
        'daily_summary_enabled' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('data-test="admin-users-page"', false)
        ->assertSeeText('Users')
        ->assertSee('value="Admin User"', false)
        ->assertSee('data-test="admin-user-name-input"', false)
        ->assertSeeText('admin@example.test')
        ->assertSee('data-test="admin-user-email-readonly"', false)
        ->assertSee('value="Operator User"', false)
        ->assertSeeText('operator@example.test')
        ->assertDontSee('data-test="admin-user-email-input"', false)
        ->assertDontSee('name="email"', false)
        ->assertSeeText('Login email is set when a user is created and cannot be changed here.')
        ->assertSee('data-test="admin-user-daily-summary-badge"', false)
        ->assertSeeText('Subscribed')
        ->assertSeeText('Not subscribed');
});

it('forbids operator users from viewing the users page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

it('redirects unauthenticated users from the users page to login', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});

it('shows the users link in navigation for admin users only', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('data-test="nav-admin-users"', false)
        ->assertSee('href="'.route('admin.users.index').'"', false)
        ->assertSeeText('Users');
});

it('does not show the users link in navigation for operator users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertDontSee('data-test="nav-admin-users"', false)
        ->assertDontSee('href="'.route('admin.users.index').'"', false);
});

it('preserves an admins own role when they submit a crafted role change', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'role' => User::ROLE_OPERATOR,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($admin->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('prevents demoting the last remaining admin', function () {
    $soleAdmin = User::factory()->admin()->create();

    $this->actingAs($soleAdmin)
        ->put(route('admin.users.update', $soleAdmin), [
            'name' => $soleAdmin->name,
            'role' => User::ROLE_OPERATOR,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($soleAdmin->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('allows an admin to demote another admin when at least one other admin remains', function () {
    $firstAdmin = User::factory()->admin()->create();
    $secondAdmin = User::factory()->admin()->create();

    $this->actingAs($firstAdmin)
        ->put(route('admin.users.update', $secondAdmin), [
            'name' => $secondAdmin->name,
            'role' => User::ROLE_OPERATOR,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($secondAdmin->fresh()->role)->toBe(User::ROLE_OPERATOR);
    expect($firstAdmin->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('allows admin users to update another users role', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create(['role' => User::ROLE_OPERATOR]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $operator), [
            'name' => $operator->name,
            'role' => User::ROLE_ADMIN,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($operator->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('allows admin users to update another users daily summary preference', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create([
        'daily_summary_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $operator), [
            'name' => $operator->name,
            'role' => User::ROLE_OPERATOR,
            'daily_summary_enabled' => '1',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($operator->fresh()->daily_summary_enabled)->toBeTrue();
});

it('allows admin users to disable daily summary for another user', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create([
        'daily_summary_enabled' => true,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $operator), [
            'name' => $operator->name,
            'role' => User::ROLE_OPERATOR,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($operator->fresh()->daily_summary_enabled)->toBeFalse();
});

it('forbids operator users from updating another user', function () {
    $operator = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($operator)
        ->put(route('admin.users.update', $other), [
            'name' => $other->name,
            'role' => User::ROLE_ADMIN,
        ])
        ->assertForbidden();

    expect($other->fresh()->role)->toBe(User::ROLE_OPERATOR);
});

it('updates last sign-in timestamp on successful login', function () {
    Carbon::setTestNow('2026-06-22 10:15:00');

    $password = 'a-long-secure-password';
    $user = User::factory()->create([
        'password' => $password,
        'last_signed_in_at' => null,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => $password,
    ])->assertRedirect(route('dashboard.index'));

    expect($user->fresh()->last_signed_in_at?->toDateTimeString())->toBe('2026-06-22 10:15:00');
});

it('displays last sign-in timestamps in the configured display timezone', function () {
    config(['app.schedule_timezone' => 'Europe/London']);

    $admin = User::factory()->admin()->create();
    User::factory()->create([
        'last_signed_in_at' => Carbon::parse('2026-06-22 10:15:00', 'UTC'),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSeeText('2026-06-22 11:15:00');
});

it('still sends daily summary emails only to subscribed users', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'subscribed@example.test',
        'daily_summary_enabled' => true,
    ]);

    User::factory()->create([
        'email' => 'unsubscribed@example.test',
        'daily_summary_enabled' => false,
    ]);

    $exitCode = Artisan::call('contextual-console:daily-summary', ['--email' => true]);

    expect($exitCode)->toBe(0);

    Mail::assertSent(ContextualConsoleDailySummaryMail::class, 1);
    Mail::assertSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('subscribed@example.test'));
    Mail::assertNotSent(ContextualConsoleDailySummaryMail::class, fn ($mail) => $mail->hasTo('unsubscribed@example.test'));
});

it('allows an admin to create a user with an email address via the create admin user command', function () {
    $exitCode = $this->artisan('contextual-console:create-admin-user', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'a-long-secure-password',
    ])->run();

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe(User::ROLE_ADMIN);
});

it('promotes an existing user to admin via the promote command', function () {
    $user = User::factory()->create([
        'email' => 'ops@example.test',
        'role' => User::ROLE_OPERATOR,
    ]);

    $exitCode = $this->artisan('contextual-console:promote-user-to-admin', [
        '--email' => 'ops@example.test',
    ])->run();

    expect($exitCode)->toBe(0);
    expect($user->fresh()->role)->toBe(User::ROLE_ADMIN);
});

it('treats promote command as idempotent for users who are already admin', function () {
    User::factory()->admin()->create([
        'email' => 'ops@example.test',
    ]);

    $exitCode = $this->artisan('contextual-console:promote-user-to-admin', [
        '--email' => 'ops@example.test',
    ])->run();

    expect($exitCode)->toBe(0);
    expect(User::query()->where('email', 'ops@example.test')->value('role'))->toBe(User::ROLE_ADMIN);
});

it('prevents an admin from changing their own email address even via crafted request', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.test',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'role' => User::ROLE_ADMIN,
            'email' => 'changed@example.test',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($admin->fresh()->email)->toBe('admin@example.test');
});

it('prevents an admin from changing another users email address even via crafted request', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create([
        'email' => 'operator@example.test',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $operator), [
            'name' => $operator->name,
            'role' => User::ROLE_OPERATOR,
            'email' => 'updated@example.test',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($operator->fresh()->email)->toBe('operator@example.test');
});

it('allows an admin to update their own name from the users page', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin User',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => 'Updated Admin',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($admin->fresh()->name)->toBe('Updated Admin');
});

it('allows an admin to update their own daily summary preference from the users page', function () {
    $admin = User::factory()->admin()->create([
        'daily_summary_enabled' => false,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'daily_summary_enabled' => '1',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($admin->fresh()->daily_summary_enabled)->toBeTrue();
});

it('shows the signed-in admins own role as read-only on the users page', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.test',
    ]);

    User::factory()->create([
        'name' => 'Operator User',
        'email' => 'operator@example.test',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('data-test="admin-user-role-readonly"', false)
        ->assertSeeText('Admin')
        ->assertSee('data-test="admin-user-role"', false);
});

it('allows an admin to update another users name from the users page', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create([
        'name' => 'Operator User',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $operator), [
            'name' => 'Updated Operator',
            'role' => User::ROLE_OPERATOR,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect($operator->fresh()->name)->toBe('Updated Operator');
});
