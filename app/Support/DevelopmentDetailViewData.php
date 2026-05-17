<?php

namespace App\Support;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;
use App\Core\Models\DatasetSnapshot;
use App\Core\Models\MonitoredSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

final class DevelopmentDetailViewData
{
    private const int RECENT_CHANGES_LIMIT = 10;

    private const int RECENT_ISSUES_LIMIT = 10;

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
     *   recentChanges: Collection<int, ChangeLog>,
     *   recentIssues: Collection<int, DatasetIssue>,
     *   plotDisplayLookupByRunId: array<int, PlotSnapshotDisplayLookup>,
     *   developmentPlotLookup: PlotSnapshotDisplayLookup,
     *   emptyPlotLookup: PlotSnapshotDisplayLookup,
     * }
     */
    public function forShow(MonitoredSource $source, string $developmentSlug): array
    {
        $developmentLabel = DevelopmentRouteSlug::decode($developmentSlug);
        $latestRun = $this->latestCompletedOrBaselineRunWithSnapshot($source);
        $emptyPlotLookup = PlotSnapshotDisplayLookup::empty();
        $developmentPlotLookup = $emptyPlotLookup;
        $plotEntityIds = [];

        if ($latestRun === null || $latestRun->current_snapshot_id === null) {
            return $this->baseResult(
                $source,
                $developmentLabel,
                $latestRun,
                'no_snapshot',
                [],
                $plotEntityIds,
                $developmentPlotLookup,
                $emptyPlotLookup,
            );
        }

        $snapshot = DatasetSnapshot::query()->find($latestRun->current_snapshot_id);
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : null;

        if ($payload === null || $payload === []) {
            return $this->baseResult(
                $source,
                $developmentLabel,
                $latestRun,
                'no_snapshot',
                [],
                $plotEntityIds,
                $developmentPlotLookup,
                $emptyPlotLookup,
            );
        }

        $developmentPlotLookup = PlotSnapshotDisplayLookup::fromPayloads($payload, null);

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
            $canonicalId = self::canonicalPlotId($item['id'] ?? null);
            if ($canonicalId !== null) {
                $plotEntityIds[$canonicalId] = true;
            }
        }

        $plotEntityIds = array_keys($plotEntityIds);

        if (! array_key_exists($developmentLabel, $knownDevelopments)) {
            return $this->baseResult(
                $source,
                $developmentLabel,
                $latestRun,
                'development_not_found',
                [],
                [],
                $developmentPlotLookup,
                $emptyPlotLookup,
            );
        }

        if ($matchingPlots === []) {
            return $this->baseResult(
                $source,
                $developmentLabel,
                $latestRun,
                'no_plots',
                [],
                [],
                $developmentPlotLookup,
                $emptyPlotLookup,
            );
        }

        usort($matchingPlots, static function (array $a, array $b): int {
            return strnatcasecmp($a['technical_id'], $b['technical_id']);
        });

        return $this->baseResult(
            $source,
            $developmentLabel,
            $latestRun,
            null,
            $matchingPlots,
            $plotEntityIds,
            $developmentPlotLookup,
            $emptyPlotLookup,
        );
    }

    /**
     * @param  list<array{
     *   plot_label: ?string,
     *   technical_id: string,
     *   status: ?string,
     *   price: mixed,
     *   bedrooms: mixed,
     *   house_type: ?string,
     *   url: ?string,
     * }>  $plots
     * @param  list<string>  $plotEntityIds
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
     *   recentChanges: Collection<int, ChangeLog>,
     *   recentIssues: Collection<int, DatasetIssue>,
     *   plotDisplayLookupByRunId: array<int, PlotSnapshotDisplayLookup>,
     *   developmentPlotLookup: PlotSnapshotDisplayLookup,
     *   emptyPlotLookup: PlotSnapshotDisplayLookup,
     * }
     */
    private function baseResult(
        MonitoredSource $source,
        string $developmentLabel,
        ?DatasetComparisonRun $latestRun,
        ?string $emptyState,
        array $plots,
        array $plotEntityIds,
        PlotSnapshotDisplayLookup $developmentPlotLookup,
        PlotSnapshotDisplayLookup $emptyPlotLookup,
    ): array {
        $recentChanges = $this->recentChangesForPlots($source, $plotEntityIds);
        $recentIssues = $this->recentIssuesForPlots($source, $plotEntityIds);

        $runIds = $recentChanges->pluck('dataset_comparison_run_id')
            ->merge($recentIssues->pluck('dataset_comparison_run_id'))
            ->unique()
            ->filter()
            ->values();

        return [
            'source' => $source,
            'developmentLabel' => $developmentLabel,
            'latestRun' => $latestRun,
            'emptyState' => $emptyState,
            'plots' => $plots,
            'recentChanges' => $recentChanges,
            'recentIssues' => $recentIssues,
            'plotDisplayLookupByRunId' => $this->plotDisplayLookupsForRuns($runIds),
            'developmentPlotLookup' => $developmentPlotLookup,
            'emptyPlotLookup' => $emptyPlotLookup,
        ];
    }

    /**
     * @param  list<string>  $plotEntityIds
     * @return Collection<int, ChangeLog>
     */
    private function recentChangesForPlots(MonitoredSource $source, array $plotEntityIds): Collection
    {
        $changeLogEntityIds = $this->numericPlotEntityIds($plotEntityIds);

        if ($changeLogEntityIds === []) {
            return new Collection;
        }

        return ChangeLog::query()
            ->with('datasetComparisonRun')
            ->where('entity_type', 'plot')
            ->whereIn('entity_id', $changeLogEntityIds)
            ->whereHas('datasetComparisonRun', static function ($query) use ($source): void {
                $query->where('source_id', $source->id);
            })
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_CHANGES_LIMIT)
            ->get();
    }

    /**
     * @param  list<string>  $plotEntityIds
     * @return Collection<int, DatasetIssue>
     */
    private function recentIssuesForPlots(MonitoredSource $source, array $plotEntityIds): Collection
    {
        if ($plotEntityIds === []) {
            return new Collection;
        }

        return DatasetIssue::query()
            ->with('datasetComparisonRun')
            ->where('monitored_source_id', $source->id)
            ->where('entity_type', 'plot')
            ->whereIn('entity_id', $plotEntityIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_ISSUES_LIMIT)
            ->get();
    }

    /**
     * @param  list<string>  $plotEntityIds
     * @return list<int>
     */
    private function numericPlotEntityIds(array $plotEntityIds): array
    {
        $ids = [];

        foreach ($plotEntityIds as $id) {
            if ($id === '' || ! is_numeric($id)) {
                continue;
            }

            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  SupportCollection<int, mixed>  $runIds
     * @return array<int, PlotSnapshotDisplayLookup>
     */
    private function plotDisplayLookupsForRuns(SupportCollection $runIds): array
    {
        if ($runIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, DatasetComparisonRun> $runs */
        $runs = DatasetComparisonRun::query()
            ->whereIn('id', $runIds->all())
            ->get()
            ->keyBy('id');

        $snapshotIds = $runs
            ->flatMap(fn (DatasetComparisonRun $run) => array_filter([
                $run->current_snapshot_id,
                $run->previous_snapshot_id,
            ]))
            ->unique()
            ->values()
            ->all();

        /** @var array<int, DatasetSnapshot> $snapshotsById */
        $snapshotsById = $snapshotIds === []
            ? []
            : DatasetSnapshot::query()
                ->whereIn('id', $snapshotIds)
                ->get()
                ->keyBy('id')
                ->all();

        $lookups = [];
        foreach ($runIds as $runId) {
            $runId = (int) $runId;
            $run = $runs->get($runId);
            if ($run === null) {
                $lookups[$runId] = PlotSnapshotDisplayLookup::empty();

                continue;
            }

            $currentSnap = $run->current_snapshot_id !== null
                ? ($snapshotsById[$run->current_snapshot_id] ?? null)
                : null;
            $previousSnap = $run->previous_snapshot_id !== null
                ? ($snapshotsById[$run->previous_snapshot_id] ?? null)
                : null;

            $lookups[$runId] = PlotSnapshotDisplayLookup::fromPayloads(
                is_array($currentSnap?->payload) ? $currentSnap->payload : null,
                is_array($previousSnap?->payload) ? $previousSnap->payload : null,
            );
        }

        return $lookups;
    }

    private static function canonicalPlotId(mixed $id): ?string
    {
        if ($id === null) {
            return null;
        }

        if (is_string($id)) {
            $trimmed = trim($id);

            return $trimmed === '' ? null : $trimmed;
        }

        return (string) $id;
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
