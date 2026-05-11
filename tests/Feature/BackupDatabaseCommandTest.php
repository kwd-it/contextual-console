<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    Carbon::setTestNow();
});

it('fails when the default connection is not SQLite', function () {
    config(['database.default' => 'mysql']);

    $exitCode = Artisan::call('contextual-console:backup-database');

    expect($exitCode)->not->toBe(0)
        ->and(Artisan::output())->toContain('not SQLite');
});

it('fails when the SQLite database file is missing', function () {
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => storage_path('app/tmp/does-not-exist-'.uniqid().'.sqlite')]);

    $exitCode = Artisan::call('contextual-console:backup-database');

    expect($exitCode)->not->toBe(0)
        ->and(Artisan::output())->toContain('does not exist');
});

it('fails when SQLite is configured as :memory:', function () {
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);

    $exitCode = Artisan::call('contextual-console:backup-database');

    expect($exitCode)->not->toBe(0)
        ->and(Artisan::output())->toContain(':memory:');
});

it('creates a compressed backup on the configured disk and removes local temp files', function () {
    File::ensureDirectoryExists(storage_path('app/tmp'));
    $dbPath = storage_path('app/tmp/backup-db-test-'.uniqid().'.sqlite');
    touch($dbPath);

    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => $dbPath]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Artisan::call('migrate', ['--force' => true]);

    Storage::fake('s3');
    config(['contextual_console.backup.disk' => 's3']);
    config(['contextual_console.backup.path' => 'database-test']);
    config(['contextual_console.backup.retention_days' => 0]);

    $exitCode = Artisan::call('contextual-console:backup-database');
    $out = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($out)->toContain('disk=s3')
        ->and($out)->toMatch('#path=database-test/contextual-console-\d{4}-\d{2}-\d{2}-\d{6}\.sqlite\.gz#');

    $remoteFiles = Storage::disk('s3')->allFiles('database-test');
    expect($remoteFiles)->not->toBeEmpty();

    $gzKey = $remoteFiles[0];
    expect($gzKey)->toEndWith('.sqlite.gz')
        ->and(Storage::disk('s3')->size($gzKey))->toBeGreaterThan(0);

    $locals = File::glob(storage_path('app/tmp/backups/contextual-console-*.sqlite*'));
    expect($locals)->toBeEmpty();

    @unlink($dbPath);
});

it('prunes backup objects older than the retention window', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-11 12:00:00', 'UTC'));

    File::ensureDirectoryExists(storage_path('app/tmp'));
    $dbPath = storage_path('app/tmp/backup-db-retention-'.uniqid().'.sqlite');
    touch($dbPath);

    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => $dbPath]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Artisan::call('migrate', ['--force' => true]);

    Storage::fake('s3');
    config(['contextual_console.backup.disk' => 's3']);
    config(['contextual_console.backup.path' => 'database-test']);
    config(['contextual_console.backup.retention_days' => 30]);

    Storage::disk('s3')->put('database-test/contextual-console-2020-01-01-000000.sqlite.gz', gzencode('stale'));

    Artisan::call('contextual-console:backup-database');

    expect(Storage::disk('s3')->exists('database-test/contextual-console-2020-01-01-000000.sqlite.gz'))->toBeFalse();

    $remaining = Storage::disk('s3')->files('database-test');
    expect($remaining)->not->toBeEmpty();

    @unlink($dbPath);
});
