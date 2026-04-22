<?php

namespace App\Domains\Housebuilder\Services;

use Illuminate\Support\Collection;

class PlotDatasetComparisonService
{
    public function __construct(
        private PlotChangeDetector $plotChangeDetector,
    ) {}

    /**
     * Compare two plot datasets keyed by plot `id`. Logs price changes for matched plots via PlotChangeDetector.
     *
     * @param  iterable<int, array<string, mixed>>  $before
     * @param  iterable<int, array<string, mixed>>  $after
     * @return array{added: int, removed: int, changed: int, unchanged: int}
     */
    public function compare(iterable $before, iterable $after): array
    {
        $beforeById = $this->plotsById($before);
        $afterById = $this->plotsById($after);

        $beforeIds = $beforeById->keys();
        $afterIds = $afterById->keys();

        $added = $afterIds->diff($beforeIds)->count();
        $removed = $beforeIds->diff($afterIds)->count();

        $changed = 0;
        $unchanged = 0;

        foreach ($beforeIds->intersect($afterIds) as $id) {
            $oldPlot = $beforeById->get($id);
            $newPlot = $afterById->get($id);

            $this->plotChangeDetector->detect($oldPlot, $newPlot);

            $oldPrice = $oldPlot['price'] ?? null;
            $newPrice = $newPlot['price'] ?? null;

            if ($oldPrice == $newPrice) {
                $unchanged++;
            } else {
                $changed++;
            }
        }

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $plots
     * @return Collection<string|int, array<string, mixed>>
     */
    private function plotsById(iterable $plots): Collection
    {
        return collect($plots)
            ->filter(fn ($plot) => is_array($plot) && array_key_exists('id', $plot))
            ->keyBy('id');
    }
}
