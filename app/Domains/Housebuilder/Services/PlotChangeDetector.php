<?php

namespace App\Domains\Housebuilder\Services;

class PlotChangeDetector
{
    public function __construct(
        private ChangeDetectionService $changeDetection,
    ) {}

    /**
     * Detect and log changes for a small explicit whitelist of comparable plot fields.
     *
     * Returns the number of field-level changes logged.
     */
    public function detect(array $oldPlot, array $newPlot): int
    {
        $plotId = $newPlot['id'] ?? $oldPlot['id'] ?? null;
        if ($plotId === null) {
            return 0;
        }

        $logged = 0;

        foreach ($this->comparableFields() as $field) {
            $oldValue = $this->normaliseComparableValue($oldPlot[$field] ?? null);
            $newValue = $this->normaliseComparableValue($newPlot[$field] ?? null);

            if ($oldValue == $newValue) {
                continue;
            }

            // Canonical plot key: the plot payload's `id` field (see PlotDatasetComparisonService::plotsById).
            $this->changeDetection->recordDomainField('plot', $plotId, $field, $oldValue, $newValue);
            $logged++;
        }

        return $logged;
    }

    /**
     * Keep this list small and explicit; only scalar/null fields.
     *
     * @return array<int, string>
     */
    private function comparableFields(): array
    {
        return [
            'price',
            'status',
        ];
    }

    private function normaliseComparableValue(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        return $value;
    }
}
