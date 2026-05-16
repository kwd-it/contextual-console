<?php

use App\Support\PlotSnapshotDisplayLookup;

it('prefers current snapshot labels over previous', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [
            ['id' => 'plots-1', 'title' => 'New title', 'development' => 'Site A'],
        ],
        [
            ['id' => 'plots-1', 'title' => 'Old title', 'development' => 'Site B'],
        ],
    );

    expect($lookup->forPlotEntity('plot', 'plots-1'))
        ->toMatchArray(['plot_label' => 'New title', 'development' => 'Site A', 'last_modified_by' => null]);
});

it('extracts last_modified_by from snapshot payloads for display', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [
            ['id' => 14, 'title' => 'Plot 14', 'last_modified_by' => 'mark'],
        ],
        null,
    );

    expect($lookup->forPlotEntity('plot', 14))
        ->toMatchArray([
            'plot_label' => 'Plot 14',
            'development' => null,
            'last_modified_by' => 'mark',
        ]);
});

it('returns null last_modified_by when the field is absent or empty', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [
            ['id' => 1, 'title' => 'Plot 1'],
            ['id' => 2, 'title' => 'Plot 2', 'last_modified_by' => ''],
            ['id' => 3, 'title' => 'Plot 3', 'last_modified_by' => null],
        ],
        null,
    );

    expect($lookup->forPlotEntity('plot', 1)['last_modified_by'])->toBeNull();
    expect($lookup->forPlotEntity('plot', 2)['last_modified_by'])->toBeNull();
    expect($lookup->forPlotEntity('plot', 3)['last_modified_by'])->toBeNull();
});

it('falls back to previous snapshot when plot is absent from current', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [
            ['id' => 'plots-2', 'title' => 'Still here'],
        ],
        [
            ['id' => 'plots-9', 'title' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm'],
        ],
    );

    expect($lookup->forPlotEntity('plot', 'plots-9'))
        ->toMatchArray(['plot_label' => 'Plot 14, The Spetisbury', 'development' => 'Charminster Farm']);
});

it('returns null for non-plot entities', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [['id' => 1, 'title' => 'X']],
        null,
    );

    expect($lookup->forPlotEntity('User', 1))->toBeNull();
});

it('decodes html entities in display labels for readability', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [
            [
                'id' => 168,
                'title' => 'Plot 168 &#8211; The Spetisbury',
                'development' => 'Charminster Farm',
            ],
        ],
        null,
    );

    expect($lookup->forPlotEntity('plot', 168))
        ->toMatchArray([
            'plot_label' => 'Plot 168 '."\u{2013}".' The Spetisbury',
            'development' => 'Charminster Farm',
        ]);
});

it('leaves plain text display labels unchanged', function () {
    $lookup = PlotSnapshotDisplayLookup::fromPayloads(
        [
            [
                'id' => 2,
                'title' => 'Plot 14, The Spetisbury',
                'development' => 'Charminster Farm',
            ],
        ],
        null,
    );

    expect($lookup->forPlotEntity('plot', 2))
        ->toMatchArray([
            'plot_label' => 'Plot 14, The Spetisbury',
            'development' => 'Charminster Farm',
        ]);
});
