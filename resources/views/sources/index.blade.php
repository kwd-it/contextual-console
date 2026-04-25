<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console</title>
        <style>
            body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji"; margin: 24px; color: #111827; }
            h1 { margin: 0 0 16px; font-size: 22px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
            th { font-weight: 600; color: #374151; font-size: 13px; }
            td { font-size: 14px; }
            .muted { color: #6b7280; }
            .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
            .details { padding-top: 2px; }
        </style>
    </head>
    <body>
        <h1>Monitored Sources</h1>

        @if (empty($summaries))
            <p class="muted">No monitored sources found.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Key</th>
                        <th>Latest run</th>
                        <th>Finished</th>
                        <th>Changes</th>
                        <th>Issues</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summaries as $s)
                        @php
                            $latestStatus = $s['latest_run_status'] ?? null;
                            $latestStatusLabel = $latestStatus ?? 'none';

                            $finishedAt = $s['latest_run_finished_at'] ?? null;
                            $finishedLabel = $finishedAt ? $finishedAt->toDateTimeString() : '-';

                            $issuesTotal = (int) ($s['issue_count'] ?? 0);
                            $errors = (int) ($s['error_count'] ?? 0);
                            $warnings = (int) ($s['warning_count'] ?? 0);
                            $infos = (int) ($s['info_count'] ?? 0);

                            $issuesLabel = (string) $issuesTotal;
                            if ($issuesTotal > 0) {
                                $parts = [];
                                if ($errors > 0) $parts[] = "error={$errors}";
                                if ($warnings > 0) $parts[] = "warning={$warnings}";
                                if ($infos > 0) $parts[] = "info={$infos}";
                                if ($parts !== []) $issuesLabel .= ' (' . implode(' ', $parts) . ')';
                            }
                        @endphp

                        <tr>
                            <td>
                                {{ $s['source_name'] ?? '' }}
                                <div class="details muted mono">
                                    Run ID: {{ $s['latest_run_id'] ?? '-' }}
                                    · Current snapshot ID: {{ $s['current_snapshot_id'] ?? '-' }}
                                    · Previous snapshot ID: {{ $s['previous_snapshot_id'] ?? '-' }}
                                </div>
                            </td>
                            <td class="mono">{{ $s['source_key'] ?? '' }}</td>
                            <td>{{ $latestStatusLabel }}</td>
                            <td class="mono">{{ $finishedLabel }}</td>
                            <td class="mono">
                                added={{ (int) ($s['added'] ?? 0) }}
                                removed={{ (int) ($s['removed'] ?? 0) }}
                                changed={{ (int) ($s['changed'] ?? 0) }}
                                unchanged={{ (int) ($s['unchanged'] ?? 0) }}
                            </td>
                            <td class="mono">{{ $issuesLabel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </body>
</html>
