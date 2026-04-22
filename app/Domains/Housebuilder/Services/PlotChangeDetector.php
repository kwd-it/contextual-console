<?php

namespace App\Domains\Housebuilder\Services;

class PlotChangeDetector
{
    public function __construct(
        private ChangeDetectionService $changeDetection,
    ) {}

    public function detect(array $oldPlot, array $newPlot): void
    {
        $oldPrice = $oldPlot['price'] ?? null;
        $newPrice = $newPlot['price'] ?? null;

        if ($oldPrice == $newPrice) {
            return;
        }

        $plotId = $newPlot['id'] ?? $oldPlot['id'] ?? null;
        if ($plotId === null) {
            return;
        }

        // Canonical plot key: the plot payload's `id` field (see PlotDatasetComparisonService::plotsById).
        $this->changeDetection->recordPlotPrice($plotId, $oldPrice, $newPrice);
    }
}
