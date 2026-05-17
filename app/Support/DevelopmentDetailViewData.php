<?php

namespace App\Support;

use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;

final class DevelopmentDetailViewData
{
    /**
     * @return array{
     *   source: MonitoredSource,
     *   developmentLabel: string,
     *   latestRun: ?DatasetComparisonRun,
     *   emptyState: null|'no_snapshot'|'development_not_found'|'no_plots',
     *   plots: list<array{
     *     plot_label: ?string,
     *     technical_id: string,
     *     status: ?string,
     *     price: mixed,
     *     bedrooms: mixed,
     *     house_type: ?string,
     *     url: ?string,
     *   }>,
     * }
     */
    public function forShow(MonitoredSource $source, string $developmentSlug): array
    {
        $developmentLabel = DevelopmentRouteSlug::decode($developmentSlug);
        $latestRun = $this->latestCompletedOrBaselineRunWithSnapshot($source);

        if ($latestRun === null || $latestRun->current_snapshot_id === null) {
            return [
                'source' => $source,
                'developmentLabel' => $developmentLabel,
                'latestRun' => $latestRun,
                'emptyState' => 'no_snapshot',
                'plots' => [],
            ];
        }

        $snapshot = DatasetSnapshot::query()->find($latestRun->current_snapshot_id);
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : null;

        if ($payload === null || $payload === []) {
            return [
                'source' => $source,
                'developmentLabel' => $developmentLabel,
                'latestRun' => $latestRun,
                'emptyState' => 'no_snapshot',
                'plots' => [],
            ];
        }

        $knownDevelopments = [];
        $matchingPlots = [];

        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }

            $plotLabel = PlotDevelopmentLabel::fromPlot($item);
            $knownDevelopments[$plotLabel] = true;

            if (! PlotDevelopmentLabel::plotMatches($item, $developmentLabel)) {
                continue;
            }

            $matchingPlots[] = $this->plotRow($item);
        }

        if (! array_key_exists($developmentLabel, $knownDevelopments)) {
            return [
                'source' => $source,
                'developmentLabel' => $developmentLabel,
                'latestRun' => $latestRun,
                'emptyState' => 'development_not_found',
                'plots' => [],
            ];
        }

        if ($matchingPlots === []) {
            return [
                'source' => $source,
                'developmentLabel' => $developmentLabel,
                'latestRun' => $latestRun,
                'emptyState' => 'no_plots',
                'plots' => [],
            ];
        }

        usort($matchingPlots, static function (array $a, array $b): int {
            return strnatcasecmp($a['technical_id'], $b['technical_id']);
        });

        return [
            'source' => $source,
            'developmentLabel' => $developmentLabel,
            'latestRun' => $latestRun,
            'emptyState' => null,
            'plots' => $matchingPlots,
        ];
    }

    private function latestCompletedOrBaselineRunWithSnapshot(MonitoredSource $source): ?DatasetComparisonRun
    {
        return DatasetComparisonRun::query()
            ->where('source_id', $source->id)
            ->whereIn('status', ['completed', 'baseline'])
            ->whereNotNull('current_snapshot_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $plot
     * @return array{
     *   plot_label: ?string,
     *   technical_id: string,
     *   status: ?string,
     *   price: mixed,
     *   bedrooms: mixed,
     *   house_type: ?string,
     *   url: ?string,
     * }
     */
    private function plotRow(array $plot): array
    {
        $technicalId = $plot['id'] ?? null;
        if ($technicalId === null || $technicalId === '') {
            $technicalId = '—';
        } elseif (! is_string($technicalId)) {
            $technicalId = (string) $technicalId;
        } else {
            $technicalId = trim($technicalId) === '' ? '—' : trim($technicalId);
        }

        $plotLabel = self::nonEmptyString($plot['title'] ?? null)
            ?? self::nonEmptyString($plot['name'] ?? null);

        $status = is_string($plot['status'] ?? null) ? trim((string) $plot['status']) : null;
        if ($status === '') {
            $status = null;
        }

        $houseType = self::nonEmptyString($plot['house_type'] ?? null);
        $url = self::nonEmptyString($plot['url'] ?? null);

        return [
            'plot_label' => $plotLabel,
            'technical_id' => $technicalId,
            'status' => $status,
            'price' => $plot['price'] ?? null,
            'bedrooms' => $plot['bedrooms'] ?? null,
            'house_type' => $houseType,
            'url' => $url,
        ];
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return html_entity_decode($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
