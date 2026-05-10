<?php

use App\Core\Models\ChangeLog;
use App\Domains\Housebuilder\Services\ChangeDetectionService;
use App\Domains\Housebuilder\Services\PlotChangeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes a change log when plot price changes', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000],
        ['id' => 1, 'price' => 110_000],
    );

    expect($logged)->toBe(1);
    expect(ChangeLog::count())->toBe(1);

    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('price');
    expect($log->old_value)->toBe('100000');
    expect($log->new_value)->toBe('110000');
});

it('does nothing when plot price is unchanged', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000],
        ['id' => 1, 'price' => 100_000],
    );

    expect($logged)->toBe(0);
    expect(ChangeLog::count())->toBe(0);
});

it('writes a change log when a whitelisted non-price field changes', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 1, 'price' => 100_000, 'status' => 'reserved'],
    );

    expect($logged)->toBe(1);
    expect(ChangeLog::count())->toBe(1);

    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe('status');
    expect($log->old_value)->toBe('available');
    expect($log->new_value)->toBe('reserved');
});

it('logs multiple field changes on the same matched plot as multiple change log entries', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 1, 'price' => 110_000, 'status' => 'reserved'],
    );

    expect($logged)->toBe(2);
    expect(ChangeLog::count())->toBe(2);

    $price = ChangeLog::query()->where('field', 'price')->firstOrFail();
    expect($price->entity_type)->toBe('plot');
    expect((int) $price->entity_id)->toBe(1);

    $status = ChangeLog::query()->where('field', 'status')->firstOrFail();
    expect($status->entity_type)->toBe('plot');
    expect((int) $status->entity_id)->toBe(1);
});

it('writes a change log for each newly tracked Housebuilder Pack plot field', function (string $field, mixed $oldValue, mixed $newValue) {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        ['id' => 1, $field => $oldValue],
        ['id' => 1, $field => $newValue],
    );

    expect($logged)->toBe(1);
    expect(ChangeLog::count())->toBe(1);

    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(1);
    expect($log->field)->toBe($field);
    expect($log->old_value)->toBe((string) $oldValue);
    expect($log->new_value)->toBe((string) $newValue);
})->with([
    'title' => ['title', 'Plot 12 - The Oakwood', 'Plot 12 - The Oakwood (Show home)'],
    'bedrooms' => ['bedrooms', 3, 4],
    'development' => ['development', 'Maple Fields', 'Maple Fields - Phase 2'],
    'house_type' => ['house_type', 'Detached', 'Semi-detached'],
    'url' => ['url', 'https://example.test/plots/12', 'https://example.test/plots/12-renamed'],
]);

it('logs multiple newly tracked field changes on the same matched plot as separate change log entries', function () {
    $detector = new PlotChangeDetector(new ChangeDetectionService);

    $logged = $detector->detect(
        [
            'id' => 1,
            'price' => 100_000,
            'status' => 'available',
            'title' => 'Plot 12 - The Oakwood',
            'bedrooms' => 3,
            'development' => 'Maple Fields',
            'house_type' => 'Detached',
            'url' => 'https://example.test/plots/12',
        ],
        [
            'id' => 1,
            'price' => 100_000, // unchanged
            'status' => 'available', // unchanged
            'title' => 'Plot 12 - The Oakwood (Show home)',
            'bedrooms' => 4,
            'development' => 'Maple Fields - Phase 2',
            'house_type' => 'Semi-detached',
            'url' => 'https://example.test/plots/12-renamed',
        ],
    );

    expect($logged)->toBe(5);
    expect(ChangeLog::count())->toBe(5);

    foreach (['title', 'bedrooms', 'development', 'house_type', 'url'] as $field) {
        expect(ChangeLog::query()
            ->where('entity_type', 'plot')
            ->where('entity_id', 1)
            ->where('field', $field)
            ->exists())->toBeTrue();
    }

    expect(ChangeLog::query()->where('field', 'price')->exists())->toBeFalse();
    expect(ChangeLog::query()->where('field', 'status')->exists())->toBeFalse();
});
