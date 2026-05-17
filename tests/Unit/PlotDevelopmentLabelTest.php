<?php

use App\Support\PlotDevelopmentLabel;

it('prefers explicit development over URL fallback', function () {
    $label = PlotDevelopmentLabel::fromPlot([
        'development' => 'Explicit Development',
        'url' => 'https://example.test/developments/other-slug/plot-1/',
    ]);

    expect($label)->toBe('Explicit Development');
});

it('uses development_name when development is blank', function () {
    $label = PlotDevelopmentLabel::fromPlot([
        'development' => '',
        'development_name' => 'Named Via development_name',
        'url' => 'https://example.test/developments/url-slug/plot-1/',
    ]);

    expect($label)->toBe('Named Via development_name');
});

it('falls back to development slug from plot URL when fields are blank', function () {
    $label = PlotDevelopmentLabel::fromPlot([
        'development' => '',
        'url' => 'https://www.wyatthomes.co.uk/developments/brimsmore-townhouse-collection/plot-241/',
    ]);

    expect($label)->toBe('Brimsmore Townhouse Collection');
});

it('title-cases URL-derived fallback labels from hyphenated slugs', function () {
    expect(PlotDevelopmentLabel::fromPlot([
        'url' => '/developments/brimsmore-townhouse-collection/plot-241/',
    ]))->toBe('Brimsmore Townhouse Collection');
});

it('decodes URL-encoded development slugs in plot URLs', function () {
    $label = PlotDevelopmentLabel::fromPlot([
        'url' => 'https://example.test/developments/brimsmore%20townhouse%20collection/plot-1/',
    ]);

    expect($label)->toBe('Brimsmore Townhouse Collection');
});

it('uses unknown fallback when development fields and URL are unusable', function () {
    expect(PlotDevelopmentLabel::fromPlot([]))->toBe(PlotDevelopmentLabel::UNKNOWN_LABEL);
    expect(PlotDevelopmentLabel::fromPlot([
        'development' => '',
        'development_name' => null,
    ]))->toBe(PlotDevelopmentLabel::UNKNOWN_LABEL);
    expect(PlotDevelopmentLabel::fromPlot([
        'url' => 'https://example.test/plots/241/',
    ]))->toBe(PlotDevelopmentLabel::UNKNOWN_LABEL);
});

it('matches plots by resolved label including URL fallback', function () {
    $plot = [
        'development' => '',
        'url' => 'https://example.test/developments/brimsmore-townhouse-collection/plot-241/',
    ];

    expect(PlotDevelopmentLabel::plotMatches($plot, 'Brimsmore Townhouse Collection'))->toBeTrue();
    expect(PlotDevelopmentLabel::plotMatches($plot, 'Unknown development'))->toBeFalse();
});
