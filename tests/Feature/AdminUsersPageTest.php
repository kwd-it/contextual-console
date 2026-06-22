<?php

use App\Mail\ContextualConsoleDailySummaryMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
        ->assertSee('data-test="admin-user-create-email-input"', false)
        ->assertSeeText('Login email is set when a user is created and cannot be changed here.')
        ->assertSee('data-test="admin-user-daily-summary-badge"', false)
        ->assertSeeText('Subscribed')
        ->assertSeeText('Not subscribed')
        ->assertSee('data-test="admin-users-create-section"', false)
        ->assertSee('data-test="admin-user-create-form"', false)
        ->assertSeeText('Initial password')
        ->assertSeeText('Contextual Console does not send invite emails or offer self-service password reset.')
        ->assertSee('data-test="admin-user-reset-details"', false)
        ->assertSee('data-test="admin-user-reset-password"', false)
        ->assertSee('data-test="admin-user-delete"', false);
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

it('allows an admin to create a user from the users page', function () {
    $admin = User::factory()->admin()->create();
    $password = 'a-long-secure-password';

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Operator',
            'email' => 'new-operator@example.test',
            'role' => User::ROLE_OPERATOR,
            'password' => $password,
            'daily_summary_enabled' => '1',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    $user = User::query()->where('email', 'new-operator@example.test')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('New Operator');
    expect($user->role)->toBe(User::ROLE_OPERATOR);
    expect($user->daily_summary_enabled)->toBeTrue();
    expect(Hash::check($password, $user->password))->toBeTrue();
});

it('validates required fields when creating a user from the users page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['name', 'email', 'role', 'password']);

    expect(User::query()->count())->toBe(1);
});

it('rejects duplicate email addresses when creating a user from the users page', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['email' => 'taken@example.test']);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => 'Duplicate User',
            'email' => 'taken@example.test',
            'role' => User::ROLE_OPERATOR,
            'password' => 'a-long-secure-password',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['email']);

    expect(User::query()->count())->toBe(2);
});

it('forbids operator users from creating users', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.users.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.test',
            'role' => User::ROLE_OPERATOR,
            'password' => 'a-long-secure-password',
        ])
        ->assertForbidden();

    expect(User::query()->count())->toBe(1);
});

it('allows an admin to reset another users password from the users page', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create();
    $newPassword = 'another-long-password';

    $this->actingAs($admin)
        ->put(route('admin.users.reset-password', $operator), [
            'password' => $newPassword,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect(Hash::check($newPassword, $operator->fresh()->password))->toBeTrue();
});

it('prevents an admin from resetting their own password from the users page', function () {
    $admin = User::factory()->admin()->create([
        'password' => 'original-long-password',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.users.reset-password', $admin), [
            'password' => 'replacement-long-password',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    expect(Hash::check('original-long-password', $admin->fresh()->password))->toBeTrue();
});

it('validates password length when resetting another users password', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create([
        'password' => 'original-long-password',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.reset-password', $operator), [
            'password' => 'short',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['password']);

    expect(Hash::check('original-long-password', $operator->fresh()->password))->toBeTrue();
});

it('forbids operator users from resetting another users password', function () {
    $operator = User::factory()->create();
    $other = User::factory()->create([
        'password' => 'original-long-password',
    ]);

    $this->actingAs($operator)
        ->put(route('admin.users.reset-password', $other), [
            'password' => 'replacement-long-password',
        ])
        ->assertForbidden();

    expect(Hash::check('original-long-password', $other->fresh()->password))->toBeTrue();
});

it('allows an admin to delete another user from the users page', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create([
        'email' => 'delete-me@example.test',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $operator))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect(User::query()->where('email', 'delete-me@example.test')->exists())->toBeFalse();
});

it('prevents an admin from deleting their own account from the users page', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@example.test',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    expect(User::query()->where('email', 'admin@example.test')->exists())->toBeTrue();
});

it('allows deleting an admin when another admin remains', function () {
    $soleAdmin = User::factory()->admin()->create([
        'email' => 'sole-admin@example.test',
    ]);
    $actingAdmin = User::factory()->admin()->create();

    $this->actingAs($actingAdmin)
        ->delete(route('admin.users.destroy', $soleAdmin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('status');

    expect(User::query()->where('email', 'sole-admin@example.test')->exists())->toBeFalse();
});

it('prevents deleting the last admin when only one admin exists', function () {
    $soleAdmin = User::factory()->admin()->create([
        'email' => 'sole-admin@example.test',
    ]);
    User::factory()->create([
        'email' => 'operator@example.test',
    ]);

    $this->actingAs($soleAdmin)
        ->delete(route('admin.users.destroy', $soleAdmin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    expect(User::query()->where('email', 'sole-admin@example.test')->exists())->toBeTrue();
});

it('forbids operator users from deleting another user', function () {
    $operator = User::factory()->create();
    $other = User::factory()->create([
        'email' => 'protected@example.test',
    ]);

    $this->actingAs($operator)
        ->delete(route('admin.users.destroy', $other))
        ->assertForbidden();

    expect(User::query()->where('email', 'protected@example.test')->exists())->toBeTrue();
});

it('hides self-service password reset and delete controls on the signed-in admins row', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.test',
    ]);

    User::factory()->create([
        'name' => 'Operator User',
        'email' => 'operator@example.test',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('data-user-id="'.$admin->id.'"');
    expect($html)->toContain('data-test="admin-user-reset-password"');
    expect($html)->toContain('data-test="admin-user-delete"');

    preg_match(
        '/<tr[^>]*data-user-id="'.$admin->id.'"[^>]*>.*?<\/tr>/s',
        $html,
        $adminRow,
    );

    expect($adminRow)->not->toBeEmpty();
    expect($adminRow[0])->not->toContain('data-test="admin-user-reset-password"');
    expect($adminRow[0])->not->toContain('data-test="admin-user-delete"');
});

it('hides delete for the only admin in the system', function () {
    $soleAdmin = User::factory()->admin()->create([
        'email' => 'sole-admin@example.test',
    ]);
    User::factory()->create([
        'email' => 'operator@example.test',
    ]);

    $response = $this->actingAs($soleAdmin)
        ->get(route('admin.users.index'))
        ->assertOk();

    $html = $response->getContent();

    preg_match(
        '/<tr[^>]*data-user-id="'.$soleAdmin->id.'"[^>]*>.*?<\/tr>/s',
        $html,
        $soleAdminRow,
    );

    expect($soleAdminRow)->not->toBeEmpty();
    expect($soleAdminRow[0])->not->toContain('data-test="admin-user-delete"');

    preg_match(
        '/<tr[^>]*data-user-id="'.User::query()->where('email', 'operator@example.test')->value('id').'"[^>]*>.*?<\/tr>/s',
        $html,
        $operatorRow,
    );

    expect($operatorRow)->not->toBeEmpty();
    expect($operatorRow[0])->toContain('data-test="admin-user-delete"');
});
