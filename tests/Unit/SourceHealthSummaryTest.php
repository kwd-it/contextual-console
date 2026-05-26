<?php

use App\Support\SourceHealthSummary;
use Tests\TestCase;

uses(TestCase::class);

it('labels a source with no runs as not run yet', function () {
    $health = SourceHealthSummary::forSummary([
        'latest_run_id' => null,
        'latest_run_status' => null,
        'error_count' => 0,
        'warning_count' => 0,
    ]);

    expect($health['key'])->toBe(SourceHealthSummary::KEY_NOT_RUN_YET)
        ->and($health['label'])->toBe('Not run yet');
});

it('labels a completed run with no error or warning issues as healthy', function () {
    $health = SourceHealthSummary::forSummary([
        'latest_run_id' => 1,
        'latest_run_status' => 'completed',
        'error_count' => 0,
        'warning_count' => 0,
    ]);

    expect($health['key'])->toBe(SourceHealthSummary::KEY_HEALTHY)
        ->and($health['label'])->toBe('Healthy');
});

it('labels a baseline run with no error or warning issues as healthy', function () {
    $health = SourceHealthSummary::forSummary([
        'latest_run_id' => 1,
        'latest_run_status' => 'baseline',
        'error_count' => 0,
        'warning_count' => 0,
    ]);

    expect($health['key'])->toBe(SourceHealthSummary::KEY_HEALTHY)
        ->and($health['label'])->toBe('Healthy');
});

it('labels a completed run with error issues as needs review', function () {
    $health = SourceHealthSummary::forSummary([
        'latest_run_id' => 1,
        'latest_run_status' => 'completed',
        'error_count' => 2,
        'warning_count' => 0,
    ]);

    expect($health['key'])->toBe(SourceHealthSummary::KEY_NEEDS_REVIEW)
        ->and($health['label'])->toBe('Needs review');
});

it('labels a completed run with warning issues as needs review', function () {
    $health = SourceHealthSummary::forSummary([
        'latest_run_id' => 1,
        'latest_run_status' => 'completed',
        'error_count' => 0,
        'warning_count' => 1,
    ]);

    expect($health['key'])->toBe(SourceHealthSummary::KEY_NEEDS_REVIEW)
        ->and($health['label'])->toBe('Needs review');
});

it('labels a failed latest run as failing', function () {
    $health = SourceHealthSummary::forSummary([
        'latest_run_id' => 1,
        'latest_run_status' => 'failed',
        'error_count' => 1,
        'warning_count' => 0,
    ]);

    expect($health['key'])->toBe(SourceHealthSummary::KEY_FAILING)
        ->and($health['label'])->toBe('Failing');
});
