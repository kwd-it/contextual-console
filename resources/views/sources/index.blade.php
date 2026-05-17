<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — Data sources</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title">@include('sources._dashboard-icon', ['name' => 'source'])<span>Data sources</span></h1>
                <p class="cc-page-sub">Each source is a tracked website feed. Compare daily snapshots to detect plot data changes and surface issues. This table shows the latest comparison run per source.</p>
            </header>

            @if (empty($summaries))
                <div class="cc-card">
                    <div class="cc-card-body--padded cc-empty">
                        <p class="cc-empty-title">No sources configured</p>
                        <p class="muted">No sources are configured yet.</p>
                    </div>
                </div>
            @else
                <div class="cc-card">
                    <div class="cc-card-header">
                        <h2 class="cc-card-title">@include('sources._dashboard-icon', ['name' => 'source'])<span>Sources</span></h2>
                        <p class="cc-card-desc">{{ count($summaries) }} configured source{{ count($summaries) === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="cc-card-body">
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Source key</th>
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
                                            if ($errors > 0) {
                                                $parts[] = "error={$errors}";
                                            }
                                            if ($warnings > 0) {
                                                $parts[] = "warning={$warnings}";
                                            }
                                            if ($infos > 0) {
                                                $parts[] = "info={$infos}";
                                            }
                                            if ($parts !== []) {
                                                $issuesLabel .= ' (' . implode(' ', $parts) . ')';
                                            }
                                        }

                                    @endphp

                                    <tr>
                                        <td>
                                            <a href="{{ route('sources.show', $s['source_id']) }}">{{ $s['source_name'] ?? '' }}</a>
                                            <div class="cc-details muted mono">
                                                Run ID: {{ $s['latest_run_id'] ?? '-' }}
                                                · Current snapshot ID: {{ $s['current_snapshot_id'] ?? '-' }}
                                                · Previous snapshot ID: {{ $s['previous_snapshot_id'] ?? '-' }}
                                            </div>
                                        </td>
                                        <td class="mono">{{ $s['source_key'] ?? '' }}</td>
                                        <td>
                                            @include('sources._dashboard-status-badge', ['status' => $latestStatus ?? '', 'label' => $latestStatusLabel])
                                        </td>
                                        <td class="mono cc-time">{{ $finishedLabel }}</td>
                                        <td>
                                            <div class="cc-stat-row mono">
                                                <span class="cc-count-pill" title="Added">added={{ (int) ($s['added'] ?? 0) }}</span>
                                                <span class="cc-count-pill" title="Removed">removed={{ (int) ($s['removed'] ?? 0) }}</span>
                                                <span class="cc-count-pill" title="Changed">changed={{ (int) ($s['changed'] ?? 0) }}</span>
                                                <span class="cc-count-pill" title="Unchanged">unchanged={{ (int) ($s['unchanged'] ?? 0) }}</span>
                                            </div>
                                        </td>
                                        <td class="mono">{{ $issuesLabel }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </body>
</html>
