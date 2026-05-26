<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console - Comparison run #{{ $run->id }} - {{ $source->display_label }}</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <div class="muted cc-back">
                <a href="{{ route('sources.show', $source) }}">← {{ $source->display_label }}</a>
            </div>

            <header class="cc-page-header">
                <h1 class="cc-page-title">@include('sources._dashboard-icon', ['name' => 'run'])<span>Comparison run #{{ $run->id }}</span></h1>
                <p class="cc-page-sub">Daily snapshot comparison for {{ $source->display_label }}. Counts and rows below reflect differences between that run's captured dataset and the previous snapshot.</p>
                <p class="cc-source-meta muted mono">Source key: {{ $source->key }}</p>
            </header>

            @if ($failedRunDiagnostics !== null)
                @include('sources._failed-run-diagnostics', ['diagnostics' => $failedRunDiagnostics])
            @endif

            <section class="cc-card" aria-labelledby="hdr-run-summary">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-run-summary">@include('sources._dashboard-icon', ['name' => 'run'])<span>Run overview</span></h2>
                    <p class="cc-card-desc">Status, timing, snapshot ids, and summary counts for this comparison run.</p>
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

                    @endphp

                    <table class="cc-kv">
                        <tbody>
                            <tr>
                                <th>Status</th>
                                <td>@include('sources._dashboard-status-badge', ['status' => $run->status, 'label' => $run->status])</td>
                            </tr>
                            <tr>
                                <th>Run id</th>
                                <td class="mono">{{ $run->id }}</td>
                            </tr>
                            <tr>
                                <th>Started at</th>
                                <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($run->started_at) }}</td>
                            </tr>
                            <tr>
                                <th>Finished at</th>
                                <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($run->finished_at) }}</td>
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
                    <h2 class="cc-card-title" id="hdr-run-issues">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Issues on this run</span></h2>
                    <p class="cc-card-desc">Data checks and ingest messages recorded for this run.</p>
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

                                    @endphp
                                    <tr>
                                        <td class="mono">
                                            @include('sources._dashboard-severity-badge', ['severity' => $issue->severity, 'label' => $issue->severity])
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
                                                    @if ($issuePlotMeta !== null && $issuePlotMeta['last_modified_by'] !== null)
                                                        <div class="cc-entity-display__secondary muted">Last modified by: {{ $issuePlotMeta['last_modified_by'] }}</div>
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
                                        <td>
                                            <a href="{{ route('issues.show', $issue) }}" data-test="run-issue-message-link">{{ $issue->message }}</a>
                                            @include('issues._issue-change-detail', ['issue' => $issue])
                                        </td>
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
                    <h2 class="cc-card-title" id="hdr-run-changes">@include('sources._dashboard-icon', ['name' => 'change'])<span>Plot data changes on this run</span></h2>
                    <p class="cc-card-desc">Field-level differences detected between snapshots for this run.</p>
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
                                                    @if ($changePlotMeta !== null && $changePlotMeta['last_modified_by'] !== null)
                                                        <div class="cc-entity-display__secondary muted">Last modified by: {{ $changePlotMeta['last_modified_by'] }}</div>
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
                                        <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($change->changed_at) }}</td>
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
