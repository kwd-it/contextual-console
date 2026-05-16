<?php

use App\Core\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects the root path to the dashboard route', function () {
    $this->get('/')
        ->assertRedirect(route('dashboard.index'));
});

it('does not return the default Laravel welcome page for the root path', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('dashboard.index'));
    expect($response->status())->not->toBe(200);
    expect($response->getContent())->not->toContain("Let's get started");
});

it('routes unauthenticated visitors from the root path through the login flow', function () {
    $this->followingRedirects()
        ->get('/')
        ->assertOk()
        ->assertSeeText('Sign in');
});

it('redirects authenticated users from the root path to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard.index'));
});

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
    ])->assertRedirect(route('dashboard.index'));

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

it('shows a log out control on authenticated pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.index'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSeeText('Log out');
});

it('allows an authenticated user to log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirects authenticated users visiting /login to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('dashboard.index'));
});
