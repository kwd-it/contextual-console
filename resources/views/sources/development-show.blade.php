<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — {{ $developmentLabel }} ({{ $source->name }})</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <div class="muted cc-back">
                <a href="{{ route('dashboard.index') }}" data-test="development-back-dashboard">← Dashboard</a>
            </div>
            <div class="muted cc-back">
                <a href="{{ route('sources.show', $source) }}" data-test="development-back-source">← {{ $source->name }}</a>
            </div>

            <header class="cc-page-header" data-test="development-detail-header">
                <h1 class="cc-page-title">{{ $developmentLabel }}</h1>
                <p class="cc-page-sub">
                    Plots in this development from <strong>{{ $source->name }}</strong>’s latest completed or baseline snapshot.
                </p>
                @if ($latestRun !== null)
                    <p class="muted mono cc-source-meta" data-test="development-detail-run-meta">
                        Based on run #{{ $latestRun->id }}
                        @if ($latestRun->finished_at !== null)
                            (finished {{ $latestRun->finished_at->toDateTimeString() }})
                        @endif
                    </p>
                @endif
            </header>

            <section class="cc-card" aria-labelledby="hdr-development-plots" data-test="development-plots-section">
                <div class="cc-card-header">
                    <h2 id="hdr-development-plots" class="cc-card-title">Plots</h2>
                    <p class="cc-card-desc">
                        Snapshot plot rows grouped under this development name (inspection only; no scoring or charts).
                    </p>
                </div>
                <div class="cc-card-body">
                    @if ($emptyState === 'no_snapshot')
                        <div class="cc-empty" data-test="development-empty-no-snapshot">
                            <p class="cc-empty-title">No snapshot data for this source</p>
                            <p class="muted">
                                A completed or baseline run with plot snapshot data is required before development plots can be listed.
                            </p>
                        </div>
                    @elseif ($emptyState === 'development_not_found')
                        <div class="cc-empty" data-test="development-empty-not-found">
                            <p class="cc-empty-title">Development not found</p>
                            <p class="muted">
                                “{{ $developmentLabel }}” does not appear in the latest snapshot for {{ $source->name }}.
                                Check the name on the <a href="{{ route('dashboard.index') }}">dashboard</a> development overview.
                            </p>
                        </div>
                    @elseif ($emptyState === 'no_plots')
                        <div class="cc-empty" data-test="development-empty-no-plots">
                            <p class="cc-empty-title">No plots for this development</p>
                            <p class="muted">
                                The development exists in the snapshot but has no matching plot rows to display.
                            </p>
                        </div>
                    @else
                        <table class="cc-table" data-test="development-plots-table">
                            <thead>
                                <tr>
                                    <th scope="col">Plot</th>
                                    <th scope="col">Technical id</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Price</th>
                                    <th scope="col">Bedrooms</th>
                                    <th scope="col">House type</th>
                                    <th scope="col">URL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($plots as $plot)
                                    <tr data-test="development-plot-row">
                                        <td>
                                            @if ($plot['plot_label'] !== null)
                                                <div class="cc-entity-display">
                                                    <div class="cc-entity-display__primary">{{ $plot['plot_label'] }}</div>
                                                </div>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                        <td class="mono" data-test="development-plot-technical-id">{{ $plot['technical_id'] }}</td>
                                        <td class="mono">{{ $plot['status'] ?? '—' }}</td>
                                        <td class="mono">{{ $plot['price'] ?? '—' }}</td>
                                        <td class="mono">{{ $plot['bedrooms'] ?? '—' }}</td>
                                        <td>{{ $plot['house_type'] ?? '—' }}</td>
                                        <td>
                                            @if ($plot['url'] !== null)
                                                <a href="{{ $plot['url'] }}" rel="noopener noreferrer" class="mono">{{ $plot['url'] }}</a>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-development-recent-changes" data-test="development-recent-changes-section">
                <div class="cc-card-header">
                    <h2 id="hdr-development-recent-changes" class="cc-card-title">Recent changes for this development</h2>
                    <p class="cc-card-desc">
                        Latest field-level changes for plots in this development on {{ $source->name }} (newest first).
                    </p>
                </div>
                <div class="cc-card-body">
                    @if ($recentChanges->isEmpty())
                        <div class="cc-empty" data-test="development-empty-no-recent-changes">
                            <p class="cc-empty-title">No recent changes for this development</p>
                            <p class="muted">Plot field changes from dataset comparisons will appear here when detected for plots in this development.</p>
                        </div>
                    @else
                        <table class="cc-table" data-test="development-recent-changes-table">
                            <thead>
                                <tr>
                                    <th scope="col">Changed at</th>
                                    <th scope="col">Plot</th>
                                    <th scope="col">Field</th>
                                    <th scope="col">Old value</th>
                                    <th scope="col">New value</th>
                                    <th scope="col">Run</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentChanges as $change)
                                    @php
                                        $runId = $change->dataset_comparison_run_id !== null
                                            ? (int) $change->dataset_comparison_run_id
                                            : null;
                                        $plotLookup = $runId !== null
                                            ? ($plotDisplayLookupByRunId[$runId] ?? $developmentPlotLookup)
                                            : $developmentPlotLookup;
                                        $changePlotMeta = $plotLookup->forPlotEntity(
                                            $change->entity_type !== null && $change->entity_type !== ''
                                                ? (string) $change->entity_type
                                                : null,
                                            $change->entity_id,
                                        );
                                    @endphp
                                    <tr data-test="development-recent-change-row">
                                        <td class="mono cc-time">{{ $change->changed_at?->toDateTimeString() ?? '—' }}</td>
                                        <td>
                                            @if (($change->entity_type ?? null) === 'plot' && $change->entity_id !== null && $change->entity_id !== '')
                                                <div class="cc-entity-display">
                                                    @if ($changePlotMeta !== null && $changePlotMeta['plot_label'] !== null)
                                                        <div class="cc-entity-display__primary">{{ $changePlotMeta['plot_label'] }}</div>
                                                    @endif
                                                    <div class="cc-entity-display__tech muted mono" data-test="development-recent-change-technical-id">
                                                        plot:{{ $change->entity_id }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="mono">—</span>
                                            @endif
                                        </td>
                                        <td class="mono">{{ $change->field }}</td>
                                        <td class="mono">{{ $change->old_value ?? '—' }}</td>
                                        <td class="mono">{{ $change->new_value ?? '—' }}</td>
                                        <td class="mono">
                                            @if ($change->dataset_comparison_run_id !== null)
                                                <a href="{{ route('sources.runs.show', [$source, $change->dataset_comparison_run_id]) }}" data-test="development-recent-change-run-link">{{ $change->dataset_comparison_run_id }}</a>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-development-recent-issues" data-test="development-recent-issues-section">
                <div class="cc-card-header">
                    <h2 id="hdr-development-recent-issues" class="cc-card-title">Recent issues for this development</h2>
                    <p class="cc-card-desc">
                        Latest validation and comparison issues for plots in this development on {{ $source->name }} (newest first).
                    </p>
                </div>
                <div class="cc-card-body">
                    @if ($recentIssues->isEmpty())
                        <div class="cc-empty" data-test="development-empty-no-recent-issues">
                            <p class="cc-empty-title">No recent issues for this development</p>
                            <p class="muted">Issues from dataset checks and comparisons will appear here when detected for plots in this development.</p>
                        </div>
                    @else
                        <table class="cc-table" data-test="development-recent-issues-table">
                            <thead>
                                <tr>
                                    <th scope="col">Created at</th>
                                    <th scope="col">Plot</th>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Issue type</th>
                                    <th scope="col">Field</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Run</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentIssues as $issue)
                                    @php
                                        $runId = $issue->dataset_comparison_run_id !== null
                                            ? (int) $issue->dataset_comparison_run_id
                                            : null;
                                        $plotLookup = $runId !== null
                                            ? ($plotDisplayLookupByRunId[$runId] ?? $developmentPlotLookup)
                                            : $developmentPlotLookup;
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
                                    @endphp
                                    <tr data-test="development-recent-issue-row">
                                        <td class="mono cc-time">{{ $issue->created_at?->toDateTimeString() ?? '—' }}</td>
                                        <td>
                                            @if (($issue->entity_type ?? null) === 'plot' && $issue->entity_id !== null && $issue->entity_id !== '')
                                                <div class="cc-entity-display">
                                                    @if ($issuePlotMeta !== null && $issuePlotMeta['plot_label'] !== null)
                                                        <div class="cc-entity-display__primary">{{ $issuePlotMeta['plot_label'] }}</div>
                                                    @endif
                                                    <div class="cc-entity-display__tech muted mono" data-test="development-recent-issue-technical-id">
                                                        plot:{{ $issue->entity_id }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="mono">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="cc-sev {{ $sevClass }}">{{ $issue->severity }}</span>
                                        </td>
                                        <td class="mono">{{ $issue->issue_type }}</td>
                                        <td class="mono">{{ $issue->field ?? '—' }}</td>
                                        <td>{{ $issue->message }}</td>
                                        <td class="mono">
                                            @if ($issue->dataset_comparison_run_id !== null)
                                                <a href="{{ route('sources.runs.show', [$source, $issue->dataset_comparison_run_id]) }}" data-test="development-recent-issue-run-link">{{ $issue->dataset_comparison_run_id }}</a>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                        </td>
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
