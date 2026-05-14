<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — Run #{{ $run->id }} — {{ $source->name }}</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            <div class="muted cc-back">
                <a href="{{ route('sources.show', $source) }}">← Back to {{ $source->name }}</a>
            </div>

            <header class="cc-page-header">
                <h1 class="cc-page-title">Comparison run #{{ $run->id }}</h1>
                <p class="cc-page-sub muted">{{ $source->name }}</p>
                <p class="cc-source-meta muted mono">Source key: {{ $source->key }}</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-run-summary">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-run-summary">Run summary</h2>
                    <p class="cc-card-desc">Metadata and counts for this comparison run.</p>
                </div>
                <div class="cc-card-body">
                    @php
                        $summary = (is_array($run->summary) && $run->status === 'completed') ? $run->summary : [];
                        $added = (int) ($summary['added'] ?? 0);
                        $removed = (int) ($summary['removed'] ?? 0);
                        $changed = (int) ($summary['changed'] ?? 0);
                        $unchanged = (int) ($summary['unchanged'] ?? 0);

                        $totalIssueCount = $runIssues->count();
                        $errorCount = (int) ($severityCounts['error'] ?? 0);
                        $warningCount = (int) ($severityCounts['warning'] ?? 0);
                        $infoCount = (int) ($severityCounts['info'] ?? 0);

                        $runStatusClass = 'cc-badge--neutral';
                        if ($run->status === 'completed') {
                            $runStatusClass = 'cc-badge--ok';
                        } elseif ($run->status === 'failed') {
                            $runStatusClass = 'cc-badge--fail';
                        } elseif (in_array($run->status, ['running', 'pending'], true)) {
                            $runStatusClass = 'cc-badge--info';
                        }
                    @endphp

                    <table class="cc-kv">
                        <tbody>
                            <tr>
                                <th>Status</th>
                                <td><span class="cc-badge {{ $runStatusClass }}">{{ $run->status }}</span></td>
                            </tr>
                            <tr>
                                <th>Run id</th>
                                <td class="mono">{{ $run->id }}</td>
                            </tr>
                            <tr>
                                <th>Started at</th>
                                <td class="mono cc-time">{{ $run->started_at?->toDateTimeString() ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Finished at</th>
                                <td class="mono cc-time">{{ $run->finished_at?->toDateTimeString() ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Current snapshot id</th>
                                <td class="mono">{{ $run->current_snapshot_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Previous snapshot id</th>
                                <td class="mono">{{ $run->previous_snapshot_id ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Summary counts</th>
                                <td>
                                    <div class="cc-stat-row mono">
                                        <span class="cc-count-pill">added={{ $added }}</span>
                                        <span class="cc-count-pill">removed={{ $removed }}</span>
                                        <span class="cc-count-pill">changed={{ $changed }}</span>
                                        <span class="cc-count-pill">unchanged={{ $unchanged }}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Issue count</th>
                                <td class="mono">{{ $totalIssueCount }}</td>
                            </tr>
                            <tr>
                                <th>Error count</th>
                                <td class="mono">{{ $errorCount }}</td>
                            </tr>
                            <tr>
                                <th>Warning count</th>
                                <td class="mono">{{ $warningCount }}</td>
                            </tr>
                            <tr>
                                <th>Info count</th>
                                <td class="mono">{{ $infoCount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-run-issues">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-run-issues">Issues for this run</h2>
                    <p class="cc-card-desc">Validation and ingest problems recorded on this run.</p>
                </div>
                <div class="cc-card-body">
                    @if ($runIssues->isEmpty())
                        <p class="muted cc-empty">No issues found for this run.</p>
                    @else
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Issue type</th>
                                    <th>Entity</th>
                                    <th>Field</th>
                                    <th>Message</th>
                                    <th>Context</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($runIssues as $issue)
                                    @php
                                        $entityLabel = '-';
                                        if (!empty($issue->entity_type) && $issue->entity_id !== null) {
                                            $entityLabel = "{$issue->entity_type}:{$issue->entity_id}";
                                        } elseif (!empty($issue->entity_type)) {
                                            $entityLabel = (string) $issue->entity_type;
                                        }

                                        $issuePlotMeta = $plotDisplayLookup->forPlotEntity(
                                            $issue->entity_type !== null && $issue->entity_type !== ''
                                                ? (string) $issue->entity_type
                                                : null,
                                            $issue->entity_id,
                                        );

                                        $context = is_array($issue->context) ? $issue->context : null;
                                        $contextLabel = ($context === null || $context === []) ? '-' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                                        $sevKey = strtolower((string) $issue->severity);
                                        $sevClass = match ($sevKey) {
                                            'error' => 'cc-sev--error',
                                            'warning' => 'cc-sev--warning',
                                            'info' => 'cc-sev--info',
                                            default => 'cc-sev--default',
                                        };
                                    @endphp
                                    <tr>
                                        <td class="mono">
                                            <span class="cc-sev {{ $sevClass }}">{{ $issue->severity }}</span>
                                        </td>
                                        <td class="mono">{{ $issue->issue_type }}</td>
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
                                        <td class="mono">{{ $contextLabel }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-run-changes">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-run-changes">Changes for this run</h2>
                    <p class="cc-card-desc">Field-level diffs linked to this run.</p>
                </div>
                <div class="cc-card-body">
                    @if ($runChanges->isEmpty())
                        <p class="muted cc-empty">No changes found for this run.</p>
                    @else
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Entity</th>
                                    <th>Field</th>
                                    <th>Old value</th>
                                    <th>New value</th>
                                    <th>Changed at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($runChanges as $change)
                                    @php
                                        $changePlotMeta = $plotDisplayLookup->forPlotEntity(
                                            $change->entity_type !== null && $change->entity_type !== ''
                                                ? (string) $change->entity_type
                                                : null,
                                            $change->entity_id,
                                        );
                                    @endphp
                                    <tr>
                                        <td>
                                            @if (($change->entity_type ?? null) === 'plot')
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
                                                <span class="mono">{{ $change->entity_id }}</span>
                                            @endif
                                        </td>
                                        <td class="mono">{{ $change->field }}</td>
                                        <td class="mono">{{ $change->old_value ?? '-' }}</td>
                                        <td class="mono">{{ $change->new_value ?? '-' }}</td>
                                        <td class="mono cc-time">{{ $change->changed_at?->toDateTimeString() ?? '-' }}</td>
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
