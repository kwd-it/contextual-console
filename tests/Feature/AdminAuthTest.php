<?php

use App\Core\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects unauthenticated users from /sources to /login', function () {
    $this->get('/sources')
        ->assertRedirect(route('login'));
});

it('redirects unauthenticated users from /sources/{source} to /login', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:auth-redirect',
        'name' => 'Auth Redirect Source',
    ]);

    $this->get(route('sources.show', $source))
        ->assertRedirect(route('login'));
});

it('loads the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeText('Sign in');
});

it('allows a valid user to log in and access /sources', function () {
    $password = 'a-long-secure-password';

    $user = User::factory()->create([
        'password' => $password,
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => $password,
    ])->assertRedirect(route('sources.index'));

    $this->assertAuthenticatedAs($user);

    $this->get(route('sources.index'))
        ->assertOk();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create([
        'password' => 'a-long-secure-password',
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('allows an authenticated user to log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirects authenticated users visiting /login to /sources', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('sources.index'));
});
