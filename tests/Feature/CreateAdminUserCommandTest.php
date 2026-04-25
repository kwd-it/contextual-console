<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a user with a hashed password', function () {
    $exitCode = $this->artisan('contextual-console:create-admin-user', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'a-long-secure-password',
    ])->run();

    expect($exitCode)->toBe(0);

    $user = User::query()->where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull();
    expect(Hash::check('a-long-secure-password', $user->password))->toBeTrue();
});

it('fails if email already exists', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $exitCode = $this->artisan('contextual-console:create-admin-user', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'a-long-secure-password',
    ])->run();

    expect($exitCode)->toBe(1);
});

it('fails if password is too short', function () {
    $exitCode = $this->artisan('contextual-console:create-admin-user', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'short-pass',
    ])->run();

    expect($exitCode)->toBe(1);
});

it('fails if required options are missing', function () {
    $exitCode = $this->artisan('contextual-console:create-admin-user', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
    ])->run();

    expect($exitCode)->toBe(1);
});

it('validates email format', function () {
    $exitCode = $this->artisan('contextual-console:create-admin-user', [
        '--name' => 'Admin',
        '--email' => 'not-an-email',
        '--password' => 'a-long-secure-password',
    ])->run();

    expect($exitCode)->toBe(1);
});
