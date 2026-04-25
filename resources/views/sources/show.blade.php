<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console</title>
        <style>
            body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 24px; color: #111827; }
            h1 { margin: 0 0 6px; font-size: 22px; }
            h2 { margin: 18px 0 8px; font-size: 16px; }
            a { color: #2563eb; text-decoration: none; }
            a:hover { text-decoration: underline; }
            table { width: 100%; border-collapse: collapse; }
            th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
            th { font-weight: 600; color: #374151; font-size: 13px; }
            td { font-size: 14px; }
            .muted { color: #6b7280; }
            .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
            .summary-table { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
            .summary-table th, .summary-table td { padding: 8px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
            .summary-table th { width: 180px; font-weight: 600; color: #6b7280; font-size: 13px; }
            .summary-table td { font-size: 14px; }
            .pill { display: inline-block; padding: 2px 8px; border: 1px solid #e5e7eb; border-radius: 999px; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="muted" style="margin-bottom: 10px;">
            <a href="{{ route('sources.index') }}">← Back to sources</a>
        </div>

        <h1>{{ $source->name }}</h1>
        <div class="muted mono" style="margin-bottom: 14px;">
            Source key: {{ $source->key }}
        </div>

        <h2>Latest run summary</h2>

        @if ($latestRun === null)
            <p class="muted">No runs found for this source.</p>
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
            @endphp

            <table class="summary-table">
                <tbody>
                    <tr>
                        <th>Status</th>
                        <td><span class="pill">{{ $latestRun->status }}</span></td>
                    </tr>
                    <tr>
                        <th>Run id</th>
                        <td class="mono">{{ $latestRun->id }}</td>
                    </tr>
                    <tr>
                        <th>Started at</th>
                        <td class="mono">{{ $latestRun->started_at?->toDateTimeString() ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Finished at</th>
                        <td class="mono">{{ $latestRun->finished_at?->toDateTimeString() ?? '-' }}</td>
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
                        <td class="mono">
                            added={{ $added }}
                            removed={{ $removed }}
                            changed={{ $changed }}
                            unchanged={{ $unchanged }}
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

        <h2>Recent runs</h2>
        @if ($recentRuns->isEmpty())
            <p class="muted">No runs found for this source.</p>
        @else
            <table>
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
                                if ($sevError > 0) $parts[] = "error={$sevError}";
                                if ($sevWarning > 0) $parts[] = "warning={$sevWarning}";
                                if ($sevInfo > 0) $parts[] = "info={$sevInfo}";
                                if ($parts !== []) $issuesLabel .= ' (' . implode(' ', $parts) . ')';
                            }
                        @endphp

                        <tr>
                            <td class="mono">{{ $run->id }}</td>
                            <td>{{ $run->status }}</td>
                            <td class="mono">{{ $run->finished_at?->toDateTimeString() ?? '-' }}</td>
                            <td class="mono">
                                added={{ $runAdded }}
                                removed={{ $runRemoved }}
                                changed={{ $runChanged }}
                                unchanged={{ $runUnchanged }}
                            </td>
                            <td class="mono">{{ $issuesLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h2>Latest run issues</h2>
        @if ($latestRun === null)
            <p class="muted">No issues found for the latest run.</p>
        @elseif ($latestRunIssues->isEmpty())
            <p class="muted">No issues found for the latest run.</p>
        @else
            <table>
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

                            $context = is_array($issue->context) ? $issue->context : null;
                            $contextLabel = ($context === null || $context === []) ? '-' : json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        @endphp
                        <tr>
                            <td class="mono">{{ $issue->severity }}</td>
                            <td class="mono">{{ $issue->issue_type }}</td>
                            <td class="mono">{{ $entityLabel }}</td>
                            <td class="mono">{{ $issue->field ?? '-' }}</td>
                            <td>{{ $issue->message }}</td>
                            <td class="mono">{{ $contextLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </body>
</html>
