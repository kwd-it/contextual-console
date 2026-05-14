<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — Plot data changes</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title">Plot data changes</h1>
                <p class="cc-page-sub">Recent field-level changes detected from daily dataset comparisons across all sources (newest first, up to {{ $changeLimit }} entries).</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-all-changes">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-all-changes">Change list</h2>
                    <p class="cc-card-desc">Differences between snapshots produced by comparison runs.</p>
                </div>
                <div class="cc-card-body">
                    @if ($changes->isEmpty())
                        <p class="muted cc-empty">No changes recorded yet.</p>
                    @else
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Changed at</th>
                                    <th>Source</th>
                                    <th>Source key</th>
                                    <th>Run</th>
                                    <th>Entity</th>
                                    <th>Field</th>
                                    <th>Old value</th>
                                    <th>New value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($changes as $change)
                                    @php
                                        $run = $change->datasetComparisonRun;
                                        $source = $run?->source;
                                        $runId = $change->dataset_comparison_run_id !== null
                                            ? (int) $change->dataset_comparison_run_id
                                            : null;
                                        $plotLookup = $runId !== null
                                            ? ($plotDisplayLookupByRunId[$runId] ?? $emptyPlotLookup)
                                            : $emptyPlotLookup;

                                        $entityLabel = '-';
                                        if (!empty($change->entity_type) && $change->entity_id !== null) {
                                            $entityLabel = "{$change->entity_type}:{$change->entity_id}";
                                        } elseif (!empty($change->entity_type)) {
                                            $entityLabel = (string) $change->entity_type;
                                        }

                                        $changePlotMeta = $plotLookup->forPlotEntity(
                                            $change->entity_type !== null && $change->entity_type !== ''
                                                ? (string) $change->entity_type
                                                : null,
                                            $change->entity_id,
                                        );

                                        $changedAt = $change->changed_at?->toDateTimeString() ?? '-';
                                    @endphp
                                    <tr>
                                        <td class="mono cc-time">
                                            {{ $changedAt }}
                                            <div class="cc-details muted mono">Change log id: {{ $change->id }}</div>
                                        </td>
                                        <td>
                                            @if ($source !== null)
                                                <a href="{{ route('sources.show', $source) }}">{{ $source->name }}</a>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                        <td class="mono">
                                            @if ($source !== null)
                                                {{ $source->key }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="mono">
                                            @if ($source !== null && $run !== null)
                                                <a href="{{ route('sources.runs.show', [$source, $run]) }}">{{ $change->dataset_comparison_run_id }}</a>
                                            @elseif ($change->dataset_comparison_run_id !== null)
                                                {{ $change->dataset_comparison_run_id }}
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (($change->entity_type ?? null) === 'plot' && $change->entity_id !== null && $change->entity_id !== '')
                                                <div class="cc-entity-display">
                                                    @if ($changePlotMeta !== null && $changePlotMeta['plot_label'] !== null)
                                                        <div class="cc-entity-display__primary">{{ $changePlotMeta['plot_label'] }}</div>
                                                    @endif
                                                    @if ($changePlotMeta !== null && $changePlotMeta['development'] !== null)
                                                        <div class="cc-entity-display__secondary muted">{{ $changePlotMeta['development'] }}</div>
                                                    @endif
                                                    <div class="cc-entity-display__tech muted mono">
                                                        Technical ID: {{ $change->entity_type }}:{{ $change->entity_id }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="mono">{{ $entityLabel }}</span>
                                            @endif
                                        </td>
                                        <td class="mono">{{ $change->field }}</td>
                                        <td class="mono">{{ $change->old_value ?? '-' }}</td>
                                        <td class="mono">{{ $change->new_value ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        </div>
    </body>
</html>
