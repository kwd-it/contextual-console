<?php

use App\Core\Models\ChangeLog;
use App\Domains\Housebuilder\Services\ChangeDetectionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('recordDomainField writes stable domain entity_type and entity_id', function () {
    $service = new ChangeDetectionService;

    $service->recordDomainField('plot', 99, 'price', 10, 20);

    expect(ChangeLog::count())->toBe(1);
    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('plot');
    expect((int) $log->entity_id)->toBe(99);
    expect($log->field)->toBe('price');
    expect($log->old_value)->toBe('10');
    expect($log->new_value)->toBe('20');
});

it('record uses the model short class name for entity_type', function () {
    $user = User::factory()->create(['name' => 'Before']);
    $service = new ChangeDetectionService;

    $service->record($user, 'name', 'Before', 'After');

    $log = ChangeLog::first();
    expect($log->entity_type)->toBe('User');
    expect((int) $log->entity_id)->toBe((int) $user->id);
    expect($log->field)->toBe('name');
});
