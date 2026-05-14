<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — {{ $source->name }}</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            <div class="muted cc-back">
                <a href="{{ route('sources.index') }}">← Back to sources</a>
            </div>

            <header class="cc-page-header">
                <h1 class="cc-page-title">{{ $source->name }}</h1>
                <p class="cc-source-meta muted mono">Source key: {{ $source->key }}</p>
            </header>

            {{-- Latest run summary --}}
            <section class="cc-card" aria-labelledby="hdr-latest-summary">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-latest-summary">Latest run summary</h2>
                    <p class="cc-card-desc">Most recent comparison run for this source.</p>
                </div>
                <div class="cc-card-body">
                    @if ($latestRun === null)
                        <p class="muted cc-empty">No runs found for this source.</p>
                    @else
                        @php
                            $summary = (is_array($latestRun->summary) && $latestRun->status === 'completed') ? $latestRun->summary : [];
                            $added = (int) ($summary['added'] ?? 0);
                            $removed = (int) ($summary['removed'] ?? 0);
                            $changed = (int) ($summary['changed'] ?? 0);
                            $unchanged = (int) ($summary['unchanged'] ?? 0);

                            $latestIssueCount = (int) ($issueCountsByRunId[$latestRun->id] ?? 0);
                            $latestSeverityCounts = $severityCountsByRunId[$latestRun->id] ?? [];
                            $errorCount = (int) ($latestSeverityCounts['error'] ?? 0);
                            $warningCount = (int) ($latestSeverityCounts['warning'] ?? 0);
                            $infoCount = (int) ($latestSeverityCounts['info'] ?? 0);

                            $runStatusClass = 'cc-badge--neutral';
                            if ($latestRun->status === 'completed') {
                                $runStatusClass = 'cc-badge--ok';
                            } elseif ($latestRun->status === 'failed') {
                                $runStatusClass = 'cc-badge--fail';
                            } elseif (in_array($latestRun->status, ['running', 'pending'], true)) {
                                $runStatusClass = 'cc-badge--info';
                            }
                        @endphp

                        <table class="cc-kv">
                            <tbody>
                                <tr>
                                    <th>Status</th>
                                    <td><span class="cc-badge {{ $runStatusClass }}">{{ $latestRun->status }}</span></td>
                                </tr>
                                <tr>
                                    <th>Run id</th>
                                    <td class="mono">{{ $latestRun->id }}</td>
                                </tr>
                                <tr>
                                    <th>Started at</th>
                                    <td class="mono cc-time">{{ $latestRun->started_at?->toDateTimeString() ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Finished at</th>
                                    <td class="mono cc-time">{{ $latestRun->finished_at?->toDateTimeString() ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Current snapshot id</th>
                                    <td class="mono">{{ $latestRun->current_snapshot_id ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Previous snapshot id</th>
                                    <td class="mono">{{ $latestRun->previous_snapshot_id ?? '-' }}</td>
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
                                    <td class="mono">{{ $latestIssueCount }}</td>
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
                    @endif
                </div>
            </section>

            {{-- Latest run issues (before changes and history for scan flow) --}}
            <section class="cc-card" aria-labelledby="hdr-latest-issues">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-latest-issues">Latest run issues</h2>
                    <p class="cc-card-desc">Validation and ingest problems recorded on the latest run.</p>
                </div>
                <div class="cc-card-body">
                    @if ($latestRun === null)
                        <p class="muted cc-empty">No issues found for the latest run.</p>
                    @elseif ($latestRunIssues->isEmpty())
                        <p class="muted cc-empty">No issues found for the latest run.</p>
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
                                @foreach ($latestRunIssues as $issue)
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

            {{-- Latest run changes --}}
            <section class="cc-card" aria-labelledby="hdr-latest-changes">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-latest-changes">Latest run changes</h2>
                    <p class="cc-card-desc">Field-level diffs linked to the latest run.</p>
                </div>
                <div class="cc-card-body">
                    @if ($latestRun === null)
                        <p class="muted cc-empty">No changes found for the latest run.</p>
                    @elseif ($latestRunChanges->isEmpty())
                        <p class="muted cc-empty">No changes found for the latest run.</p>
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
                                @foreach ($latestRunChanges as $change)
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

            {{-- Recent runs --}}
            <section class="cc-card" aria-labelledby="hdr-recent-runs">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-recent-runs">Recent runs</h2>
                    <p class="cc-card-desc">Up to ten most recent runs, newest first.</p>
                </div>
                <div class="cc-card-body">
                    @if ($recentRuns->isEmpty())
                        <p class="muted cc-empty">No runs found for this source.</p>
                    @else
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Run ID</th>
                                    <th>Status</th>
                                    <th>Finished</th>
                                    <th>Summary</th>
                                    <th>Issues</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentRuns as $run)
                                    @php
                                        $runSummary = (is_array($run->summary) && $run->status === 'completed') ? $run->summary : [];
                                        $runAdded = (int) ($runSummary['added'] ?? 0);
                                        $runRemoved = (int) ($runSummary['removed'] ?? 0);
                                        $runChanged = (int) ($runSummary['changed'] ?? 0);
                                        $runUnchanged = (int) ($runSummary['unchanged'] ?? 0);

                                        $runIssueCount = (int) ($issueCountsByRunId[$run->id] ?? 0);
                                        $sev = $severityCountsByRunId[$run->id] ?? [];
                                        $sevError = (int) ($sev['error'] ?? 0);
                                        $sevWarning = (int) ($sev['warning'] ?? 0);
                                        $sevInfo = (int) ($sev['info'] ?? 0);

                                        $issuesLabel = (string) $runIssueCount;
                                        if ($runIssueCount > 0) {
                                            $parts = [];
                                            if ($sevError > 0) {
                                                $parts[] = "error={$sevError}";
                                            }
                                            if ($sevWarning > 0) {
                                                $parts[] = "warning={$sevWarning}";
                                            }
                                            if ($sevInfo > 0) {
                                                $parts[] = "info={$sevInfo}";
                                            }
                                            if ($parts !== []) {
                                                $issuesLabel .= ' (' . implode(' ', $parts) . ')';
                                            }
                                        }

                                        $rowStatusClass = 'cc-badge--neutral';
                                        if ($run->status === 'completed') {
                                            $rowStatusClass = 'cc-badge--ok';
                                        } elseif ($run->status === 'failed') {
                                            $rowStatusClass = 'cc-badge--fail';
                                        } elseif (in_array($run->status, ['running', 'pending'], true)) {
                                            $rowStatusClass = 'cc-badge--info';
                                        }
                                    @endphp

                                    <tr>
                                        <td class="mono">{{ $run->id }}</td>
                                        <td>
                                            <span class="cc-badge {{ $rowStatusClass }}">{{ $run->status }}</span>
                                        </td>
                                        <td class="mono cc-time">{{ $run->finished_at?->toDateTimeString() ?? '-' }}</td>
                                        <td>
                                            <div class="cc-stat-row mono">
                                                <span class="cc-count-pill">added={{ $runAdded }}</span>
                                                <span class="cc-count-pill">removed={{ $runRemoved }}</span>
                                                <span class="cc-count-pill">changed={{ $runChanged }}</span>
                                                <span class="cc-count-pill">unchanged={{ $runUnchanged }}</span>
                                            </div>
                                        </td>
                                        <td class="mono">{{ $issuesLabel }}</td>
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
