<?php

use App\Domains\Housebuilder\Services\PlotDatasetIssueDetector;

it('returns no issues for a valid payload', function () {
    $payload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
        ['id' => 2, 'price' => 200_000, 'status' => 'reserved'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toBe([]);
});

it('detects a missing id', function () {
    $payload = [
        ['price' => 100_000, 'status' => 'available'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => null,
        'field' => 'id',
        'issue_type' => 'missing_required_field',
        'severity' => 'error',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 0]);
});

it('detects a duplicate id', function () {
    $payload = [
        ['id' => 10, 'price' => 100_000, 'status' => 'available'],
        ['id' => 10, 'price' => 200_000, 'status' => 'reserved'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => '10',
        'field' => 'id',
        'issue_type' => 'duplicate_value',
        'severity' => 'error',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 1, 'original_index' => 0]);
});

it('detects a missing price', function () {
    $payload = [
        ['id' => 1, 'status' => 'available'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => '1',
        'field' => 'price',
        'issue_type' => 'missing_required_field',
        'severity' => 'warning',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 0]);
});

it('detects an invalid non-numeric price', function () {
    $payload = [
        ['id' => 1, 'price' => 'not-a-number', 'status' => 'available'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => '1',
        'field' => 'price',
        'issue_type' => 'invalid_value',
        'severity' => 'warning',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 0, 'received' => 'not-a-number']);
});

it('detects a negative price', function () {
    $payload = [
        ['id' => 1, 'price' => -1, 'status' => 'available'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => '1',
        'field' => 'price',
        'issue_type' => 'invalid_value',
        'severity' => 'warning',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 0, 'received' => -1]);
});

it('detects a missing status', function () {
    $payload = [
        ['id' => 1, 'price' => 100_000],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => '1',
        'field' => 'status',
        'issue_type' => 'missing_required_field',
        'severity' => 'warning',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 0]);
});

it('detects an invalid status', function () {
    $payload = [
        ['id' => 1, 'price' => 100_000, 'status' => 'pending'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => '1',
        'field' => 'status',
        'issue_type' => 'invalid_value',
        'severity' => 'warning',
    ]);
    expect($issues[0]['context'])->toMatchArray([
        'index' => 0,
        'received' => 'pending',
        'allowed' => ['available', 'reserved', 'sold'],
    ]);
});

it('detects a non-array payload item as an invalid record and does not crash', function () {
    $payload = [
        'not-an-array',
        ['id' => 1, 'price' => 100_000, 'status' => 'available'],
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(1);
    expect($issues[0])->toMatchArray([
        'entity_type' => 'plot',
        'entity_id' => null,
        'field' => null,
        'issue_type' => 'invalid_record',
        'severity' => 'error',
        'message' => 'Plot payload item must be an object/array.',
    ]);
    expect($issues[0]['context'])->toMatchArray(['index' => 0, 'received_type' => 'string']);
});

it('can return multiple issues from one payload', function () {
    $payload = [
        ['price' => null, 'status' => 'pending'], // missing id + missing price + invalid status
        ['id' => 5, 'price' => 'nope', 'status' => 'available'], // invalid price
        ['id' => 5, 'price' => 100_000, 'status' => 'sold'], // duplicate id
    ];

    $issues = app(PlotDatasetIssueDetector::class)->detect($payload);

    expect($issues)->toHaveCount(5);

    expect(collect($issues)->where('field', 'id')->pluck('issue_type')->values()->all())
        ->toBe(['missing_required_field', 'duplicate_value']);

    expect(collect($issues)->where('field', 'price')->pluck('issue_type')->values()->all())
        ->toBe(['missing_required_field', 'invalid_value']);

    expect(collect($issues)->where('field', 'status')->pluck('issue_type')->values()->all())
        ->toBe(['invalid_value']);
});
