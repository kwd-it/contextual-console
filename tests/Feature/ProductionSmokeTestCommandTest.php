<?php

use App\Core\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('passes the smoke test when production prerequisites exist', function () {
    config()->set('app.url', 'https://contextual-console.example.test');
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');
    config()->set('mail.from.address', 'no-reply@example.test');

    User::factory()->create();

    MonitoredSource::create([
        'key' => 'hb:smoke-ok',
        'name' => 'Smoke OK',
        'endpoint_url' => 'https://example.test/endpoint',
    ]);

    $this->artisan('contextual-console:smoke-test')
        ->expectsOutputToContain('[OK] APP_ENV=')
        ->expectsOutputToContain('[OK] Database connection')
        ->expectsOutputToContain('[OK] Migrations table accessible')
        ->assertExitCode(0);
});

it('fails when no admin user exists', function () {
    config()->set('app.url', 'https://contextual-console.example.test');
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');
    config()->set('mail.from.address', 'no-reply@example.test');

    MonitoredSource::create([
        'key' => 'hb:smoke-user-missing',
        'name' => 'Smoke Missing User',
        'endpoint_url' => 'https://example.test/endpoint',
    ]);

    $this->artisan('contextual-console:smoke-test')
        ->expectsOutputToContain('[FAIL] Admin user exists')
        ->assertExitCode(1);
});

it('fails when no monitored source endpoint exists', function () {
    config()->set('app.url', 'https://contextual-console.example.test');
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');
    config()->set('mail.from.address', 'no-reply@example.test');

    User::factory()->create();

    MonitoredSource::create([
        'key' => 'hb:smoke-no-endpoint',
        'name' => 'Smoke No Endpoint',
        'endpoint_url' => null,
    ]);

    $this->artisan('contextual-console:smoke-test')
        ->expectsOutputToContain('[FAIL] Monitored source endpoint_url exists')
        ->assertExitCode(1);
});

it('fails when daily summary recipient is missing', function () {
    config()->set('app.url', 'https://contextual-console.example.test');
    config()->set('contextual_console.daily_summary_to', '');
    config()->set('mail.from.address', 'no-reply@example.test');

    User::factory()->create();

    MonitoredSource::create([
        'key' => 'hb:smoke-no-recipient',
        'name' => 'Smoke No Recipient',
        'endpoint_url' => 'https://example.test/endpoint',
    ]);

    $this->artisan('contextual-console:smoke-test')
        ->expectsOutputToContain('[FAIL] Daily summary recipient is set')
        ->assertExitCode(1);
});
