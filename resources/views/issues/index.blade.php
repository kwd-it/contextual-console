<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — Issues</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title">All issues</h1>
                <p class="cc-page-sub">Recent dataset issues across all monitored sources and runs (up to {{ $issueLimit }}, newest first).</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-all-issues">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-all-issues">Dataset issues</h2>
                    <p class="cc-card-desc">Validation and ingest problems from comparison runs.</p>
                </div>
                <div class="cc-card-body">
                    @if ($issues->isEmpty())
                        <p class="muted cc-empty">No issues recorded yet.</p>
                    @else
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Issue type</th>
                                    <th>Source</th>
                                    <th>Source key</th>
                                    <th>Run</th>
                                    <th>Entity</th>
                                    <th>Field</th>
                                    <th>Message</th>
                                    <th>Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($issues as $issue)
                                    @php
                                        $source = $issue->monitoredSource;
                                        $run = $issue->datasetComparisonRun;
                                        $runId = (int) $issue->dataset_comparison_run_id;
                                        $plotLookup = $plotDisplayLookupByRunId[$runId] ?? $emptyPlotLookup;

                                        $entityLabel = '-';
                                        if (!empty($issue->entity_type) && $issue->entity_id !== null) {
                                            $entityLabel = "{$issue->entity_type}:{$issue->entity_id}";
                                        } elseif (!empty($issue->entity_type)) {
                                            $entityLabel = (string) $issue->entity_type;
                                        }

                                        $issuePlotMeta = $plotLookup->forPlotEntity(
                                            $issue->entity_type !== null && $issue->entity_type !== ''
                                                ? (string) $issue->entity_type
                                                : null,
                                            $issue->entity_id,
                                        );

                                        $sevKey = strtolower((string) $issue->severity);
                                        $sevClass = match ($sevKey) {
                                            'error' => 'cc-sev--error',
                                            'warning' => 'cc-sev--warning',
                                            'info' => 'cc-sev--info',
                                            default => 'cc-sev--default',
                                        };

                                        $recordedAt = $issue->created_at?->toDateTimeString() ?? '-';
                                    @endphp
                                    <tr>
                                        <td class="mono">
                                            <span class="cc-sev {{ $sevClass }}">{{ $issue->severity }}</span>
                                        </td>
                                        <td class="mono">{{ $issue->issue_type }}</td>
                                        <td>
                                            @if ($source !== null)
                                                <a href="{{ route('sources.show', $source) }}">{{ $source->name }}</a>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                            <div class="cc-details muted mono">Issue id: {{ $issue->id }}</div>
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
                                                <a href="{{ route('sources.runs.show', [$source, $run]) }}">{{ $issue->dataset_comparison_run_id }}</a>
                                            @else
                                                {{ $issue->dataset_comparison_run_id }}
                                            @endif
                                            @if ($issue->dataset_snapshot_id !== null)
                                                <div class="cc-details muted">Snapshot id: {{ $issue->dataset_snapshot_id }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if (($issue->entity_type ?? null) === 'plot' && $issue->entity_id !== null && $issue->entity_id !== '')
                                                <div class="cc-entity-display">
                                                    @if ($issuePlotMeta !== null && $issuePlotMeta['plot_label'] !== null)
                                                        <div class="cc-entity-display__primary">{{ $issuePlotMeta['plot_label'] }}</div>
                                                    @endif
                                                    @if ($issuePlotMeta !== null && $issuePlotMeta['development'] !== null)
                                                        <div class="cc-entity-display__secondary muted">{{ $issuePlotMeta['development'] }}</div>
                                                    @endif
                                                    <div class="cc-entity-display__tech muted mono">
                                                        Technical ID: {{ $issue->entity_type }}:{{ $issue->entity_id }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="mono">{{ $entityLabel }}</span>
                                            @endif
                                        </td>
                                        <td class="mono">{{ $issue->field ?? '-' }}</td>
                                        <td>{{ $issue->message }}</td>
                                        <td class="mono cc-time">{{ $recordedAt }}</td>
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
