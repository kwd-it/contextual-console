<?php

use App\Core\Models\MonitoredSource;
use App\Domains\Housebuilder\Services\PlotHttpIngestNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns records unchanged when no adapter is set', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:norm-none',
        'name' => 'Norm None',
    ]);

    $records = [['post_id' => 1, 'foo' => 'bar']];
    $out = app(PlotHttpIngestNormalizer::class)->normalize($source, $records);

    expect($out)->toBe($records);
});

it('maps contextualwp-style rows onto console plot fields', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:norm-cwp',
        'name' => 'Norm CWP',
        'http_plot_payload_adapter' => PlotHttpIngestNormalizer::ADAPTER_CONTEXTUALWP_LIST_CONTEXTS,
    ]);

    $records = [
        ['post_id' => 42, 'acf' => ['price' => 199_000, 'status' => 'available']],
    ];

    $out = app(PlotHttpIngestNormalizer::class)->normalize($source, $records);

    expect($out[0])->toMatchArray([
        'id' => 42,
        'price' => 199_000,
        'status' => 'available',
    ]);
});

it('prefers acf.status.value over label and lowercases status for the issue detector', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:norm-acf-status',
        'name' => 'Norm ACF status',
        'http_plot_payload_adapter' => PlotHttpIngestNormalizer::ADAPTER_CONTEXTUALWP_LIST_CONTEXTS,
    ]);

    $records = [
        ['post_id' => 1, 'acf' => ['status' => ['value' => 'reserved', 'label' => 'Reserved']]],
    ];

    $out = app(PlotHttpIngestNormalizer::class)->normalize($source, $records);

    expect($out[0]['status'])->toBe('reserved');
});

it('uses acf.plot_status.label when value paths are absent and normalises title case', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:norm-plot-status-label',
        'name' => 'Norm plot status label',
        'http_plot_payload_adapter' => PlotHttpIngestNormalizer::ADAPTER_CONTEXTUALWP_LIST_CONTEXTS,
    ]);

    $records = [
        ['post_id' => 2, 'acf' => ['plot_status' => ['label' => 'Sold']]],
    ];

    $out = app(PlotHttpIngestNormalizer::class)->normalize($source, $records);

    expect($out[0]['status'])->toBe('sold');
});

it('passes through non-array items without crashing', function () {
    $source = MonitoredSource::create([
        'key' => 'hb:norm-scalar',
        'name' => 'Norm Scalar',
        'http_plot_payload_adapter' => PlotHttpIngestNormalizer::ADAPTER_CONTEXTUALWP_LIST_CONTEXTS,
    ]);

    $out = app(PlotHttpIngestNormalizer::class)->normalize($source, ['not-an-array']);

    expect($out[0])->toBe('not-an-array');
});
