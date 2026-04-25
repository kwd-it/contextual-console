<?php

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function writeTempJsonFile(array|string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'cc-payload-');
    if ($path === false) {
        throw new RuntimeException('Unable to create temp file.');
    }

    $data = is_string($contents) ? $contents : json_encode($contents);
    if ($data === false) {
        throw new RuntimeException('Unable to encode JSON.');
    }

    file_put_contents($path, $data);

    return $path;
}

it('runs a successful baseline run from a JSON file', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:manual-baseline',
        'name' => 'Manual Baseline',
    ]);

    $file = writeTempJsonFile([
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => $source->key,
        '--file' => $file,
    ])->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);

    expect(DatasetSnapshot::query()->where('source_id', $source->id)->count())->toBe(1);
    expect(DatasetComparisonRun::query()->where('source_id', $source->id)->count())->toBe(1);

    $run = DatasetComparisonRun::query()->where('source_id', $source->id)->latest('id')->firstOrFail();
    expect($run->status)->toBe('baseline');
    expect($run->previous_snapshot_id)->toBeNull();
    expect($run->summary)->toBeNull();
    expect(ChangeLog::count())->toBe(0);
});

it('runs a successful completed run from a JSON file after a baseline exists', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:manual-completed',
        'name' => 'Manual Completed',
    ]);

    $baselineFile = writeTempJsonFile([
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);

    $changedFile = writeTempJsonFile([
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
        ['id' => 2, 'price' => 200_000, 'status' => 'available'],
    ]);

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => $source->key,
        '--file' => $baselineFile,
    ])->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => $source->key,
        '--file' => $changedFile,
    ])->expectsOutputToContain('Issues: 0')
        ->assertExitCode(0);

    expect(DatasetSnapshot::query()->where('source_id', $source->id)->count())->toBe(2);
    expect(DatasetComparisonRun::query()->where('source_id', $source->id)->count())->toBe(2);

    $run2 = DatasetComparisonRun::query()->where('source_id', $source->id)->latest('id')->firstOrFail();
    expect($run2->status)->toBe('completed');
    expect($run2->previous_snapshot_id)->not->toBeNull();

    expect($run2->summary)->toBe([
        'added' => 1,
        'removed' => 0,
        'changed' => 1,
        'unchanged' => 0,
        'added_ids' => [2],
        'removed_ids' => [],
    ]);

    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'price')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 1)->where('field', 'status')->exists())->toBeTrue();
    expect(ChangeLog::query()->where('entity_type', 'plot')->where('entity_id', 2)->where('field', 'presence')->exists())->toBeTrue();
});

it('prints issue counts (and severity breakdown) when invalid payload data is supplied', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:manual-invalid-payload',
        'name' => 'Manual Invalid Payload',
    ]);

    $file = writeTempJsonFile([
        'bad-record',
        ['price' => 100_000, 'status' => 'available'], // missing id
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ]);

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => $source->key,
        '--file' => $file,
    ])->expectsOutputToContain('Issues: 2')
        ->expectsOutputToContain('- error: 2')
        ->assertExitCode(0);
});

it('fails with a useful message when the source key is unknown', function () {
    $file = writeTempJsonFile([
        ['id' => 1, 'price' => 100_000],
    ]);

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => 'hb:does-not-exist',
        '--file' => $file,
    ])->expectsOutputToContain('Monitored source not found')
        ->assertExitCode(1);

    expect(DatasetComparisonRun::count())->toBe(0);
    expect(DatasetSnapshot::count())->toBe(0);
});

it('fails with a useful message when the JSON is invalid', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:manual-invalid-json',
        'name' => 'Manual Invalid JSON',
    ]);

    $file = writeTempJsonFile('{"not valid json"');

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => $source->key,
        '--file' => $file,
    ])->expectsOutputToContain('Invalid JSON payload')
        ->assertExitCode(1);

    expect(DatasetComparisonRun::count())->toBe(0);
    expect(DatasetSnapshot::count())->toBe(0);
});

it('fails with a useful message when the file is missing', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:manual-missing-file',
        'name' => 'Manual Missing File',
    ]);

    $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cc-missing-'.uniqid().'.json';

    $this->artisan('contextual-console:run-plot-source', [
        'sourceKey' => $source->key,
        '--file' => $missing,
    ])->expectsOutputToContain('Payload file not found')
        ->assertExitCode(1);

    expect(DatasetComparisonRun::count())->toBe(0);
    expect(DatasetSnapshot::count())->toBe(0);
});

