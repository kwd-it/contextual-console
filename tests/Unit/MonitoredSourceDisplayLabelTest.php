<?php

use App\Core\Models\MonitoredSource;
use Tests\TestCase;

uses(TestCase::class);

it('returns display_name when set', function () {
    $source = MonitoredSource::make([
        'key' => 'wyatt:housebuilder',
        'name' => 'Wyatt Homes Housebuilder',
        'display_name' => 'Wyatt Homes',
    ]);

    expect($source->display_label)->toBe('Wyatt Homes');
});

it('falls back to name when display_name is empty', function () {
    $source = MonitoredSource::make([
        'key' => 'hb:example',
        'name' => 'Housebuilder Example',
        'display_name' => null,
    ]);

    expect($source->display_label)->toBe('Housebuilder Example');
});

it('falls back to name when display_name is blank whitespace', function () {
    $source = MonitoredSource::make([
        'key' => 'hb:blank-display',
        'name' => 'Fallback Name',
        'display_name' => '   ',
    ]);

    expect($source->display_label)->toBe('Fallback Name');
});
