<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console — Dashboard</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <header class="cc-page-header">
                <h1 class="cc-page-title">@include('sources._dashboard-icon', ['name' => 'dashboard'])<span>Dashboard</span></h1>
                <p class="cc-page-sub">
                    Summary of monitored website datasets: comparison runs, detected plot data changes, and validation issues from daily snapshots.
                </p>
            </header>

            <section class="cc-stat-grid" aria-label="Monitoring summary">
                <article class="cc-stat-card">
                    <p class="cc-stat-card__label">@include('sources._dashboard-icon', ['name' => 'source', 'class' => 'cc-stat-icon'])<span>Monitored sources</span></p>
                    <p class="cc-stat-card__value" data-test="dashboard-total-sources">{{ $totalSources }}</p>
                    <p class="cc-stat-card__hint muted">Total configured data sources</p>
                    <p class="cc-stat-card__action">
                        <a href="{{ route('sources.index') }}" data-test="dashboard-drill-sources-total">View</a>
                    </p>
                </article>
                <article class="cc-stat-card">
                    <p class="cc-stat-card__label">@include('sources._dashboard-icon', ['name' => 'check', 'class' => 'cc-stat-icon'])<span>Latest completed run</span></p>
                    <p class="cc-stat-card__value cc-stat-card__value--text" data-test="dashboard-latest-completed">
                        @if ($latestCompletedRunFinishedAt !== null)
                            <span class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($latestCompletedRunFinishedAt) }}</span>
                        @else
                            <span class="muted">-</span>
                        @endif
                    </p>
                    <p class="cc-stat-card__hint muted">Finished time (completed runs only)</p>
                </article>
                <article class="cc-stat-card">
                    <p class="cc-stat-card__label">@include('sources._dashboard-icon', ['name' => 'cross', 'class' => 'cc-stat-icon'])<span>Run failures</span></p>
                    <p class="cc-stat-card__value" data-test="dashboard-failed-runs-current">{{ $currentFailedRuns }}</p>
                    <p class="cc-stat-card__hint muted" data-test="dashboard-failed-runs-hint">
                        @if ($currentFailedRuns > 0)
                            Latest run still failed for {{ $currentFailedRuns === 1 ? 'one source' : $currentFailedRuns.' sources' }}
                        @elseif ($recoveredFailedRuns7d > 0)
                            <span data-test="dashboard-failed-runs-recovered">{{ $recoveredFailedRuns7d }}</span> recovered in the last 7 days
                        @else
                            No current or recent run failures
                        @endif
                    </p>
                    @if ($recoveredFailedRuns7d > 0 && $currentFailedRuns > 0)
                        <p class="cc-stat-card__hint muted"><span data-test="dashboard-failed-runs-recovered">{{ $recoveredFailedRuns7d }}</span> recovered in the last 7 days</p>
                    @endif
                    @if ($currentFailedRuns > 0)
                        <p class="cc-stat-card__action">
                            <a href="{{ route('sources.index') }}" data-test="dashboard-drill-failed-sources">View sources</a>
                        </p>
                    @endif
                </article>
                <article class="cc-stat-card cc-stat-card--issues-summary">
                    <p class="cc-stat-card__label">@include('sources._dashboard-icon', ['name' => 'issue', 'class' => 'cc-stat-icon'])<span>Active issues</span></p>
                    <p class="cc-stat-card__value" data-test="dashboard-active-issues">{{ $activeIssuesCount }}</p>
                    <p class="cc-stat-card__hint muted cc-stat-card__breakdown">
                        <span data-test="dashboard-active-info">{{ $activeInfosCount }} info</span>
                        <span class="cc-stat-card__breakdown-sep" aria-hidden="true">|</span>
                        <span data-test="dashboard-active-warnings">{{ $activeWarningsCount }} warnings</span>
                        <span class="cc-stat-card__breakdown-sep" aria-hidden="true">|</span>
                        <span data-test="dashboard-active-errors">{{ $activeErrorsCount }} errors</span>
                    </p>
                    <p class="cc-stat-card__action cc-stat-card__action--split">
                        @if ($activeIssuesCount > 0)
                            <a href="{{ route('issues.index', ['issue_status' => \App\Core\Models\DatasetIssue::FILTER_ACTIVE]) }}" data-test="dashboard-drill-active-issues">Active issues</a>
                        @else
                            <span class="muted" data-test="dashboard-active-issues-none">No active issues</span>
                        @endif
                        <span class="cc-stat-card__action-sep" aria-hidden="true">|</span>
                        @if ($activeInfosCount > 0)
                            <a href="{{ route('issues.index', ['issue_status' => \App\Core\Models\DatasetIssue::FILTER_ACTIVE, 'severity' => 'info']) }}" data-test="dashboard-drill-active-info">Info</a>
                        @else
                            <span class="muted" data-test="dashboard-active-info-none">Info</span>
                        @endif
                        <span class="cc-stat-card__action-sep" aria-hidden="true">|</span>
                        @if ($activeWarningsCount > 0)
                            <a href="{{ route('issues.index', ['issue_status' => \App\Core\Models\DatasetIssue::FILTER_ACTIVE, 'severity' => 'warning']) }}" data-test="dashboard-drill-active-warnings">Warnings</a>
                        @else
                            <span class="muted" data-test="dashboard-active-warnings-none">Warnings</span>
                        @endif
                        <span class="cc-stat-card__action-sep" aria-hidden="true">|</span>
                        @if ($activeErrorsCount > 0)
                            <a href="{{ route('issues.index', ['issue_status' => \App\Core\Models\DatasetIssue::FILTER_ACTIVE, 'severity' => 'error']) }}" data-test="dashboard-drill-active-errors">Errors</a>
                        @else
                            <span class="muted" data-test="dashboard-active-errors-none">Errors</span>
                        @endif
                    </p>
                </article>
                <article class="cc-stat-card">
                    <p class="cc-stat-card__label">@include('sources._dashboard-icon', ['name' => 'change', 'class' => 'cc-stat-icon'])<span>Plot data changes (7 days)</span></p>
                    <p class="cc-stat-card__value" data-test="dashboard-changes-7d">{{ $changesLast7Days }}</p>
                    <p class="cc-stat-card__hint muted">Field-level changes logged</p>
                    <p class="cc-stat-card__action">
                        <a href="{{ route('changes.index', ['date_from' => $summaryDateFrom]) }}" data-test="dashboard-drill-changes-7d">View</a>
                    </p>
                </article>
            </section>

            <section class="cc-card" aria-labelledby="hdr-development-overview" data-test="dashboard-development-overview">
                <div class="cc-card-header">
                    <h2 id="hdr-development-overview" class="cc-card-title">@include('sources._dashboard-icon', ['name' => 'development'])<span>Development overview</span></h2>
                    <p class="cc-card-desc">
                        Plot counts by development from each source’s latest completed or baseline snapshot (top groups by plot count).
                    </p>
                </div>
                <div class="cc-card-body">
                    @if ($developmentOverviewGroups === [])
                        <div class="cc-empty" data-test="dashboard-development-overview-empty">
                            <p class="cc-empty-title">No snapshot development data</p>
                            <p class="muted">Development groupings appear here after a source has a completed or baseline run with plot snapshot data.</p>
                        </div>
                    @else
                        @php
                            $showComingSoonColumn = collect($developmentOverviewGroups)->contains(
                                fn ($row) => ($row['coming_soon'] ?? 0) > 0,
                            );
                        @endphp
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th scope="col">Source</th>
                                    <th scope="col">Development</th>
                                    <th scope="col">Total</th>
                                    <th scope="col">Available</th>
                                    <th scope="col">Reserved</th>
                                    <th scope="col">Sold</th>
                                    @if ($showComingSoonColumn)
                                        <th scope="col">Coming soon</th>
                                    @endif
                                    <th scope="col">Unknown</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($developmentOverviewGroups as $group)
                                    @php
                                        $devSource = $group['source'];
                                    @endphp
                                    <tr data-test="dashboard-development-overview-row">
                                        <td>
                                            <a href="{{ route('sources.show', $devSource) }}" data-test="dashboard-development-overview-source-link">{{ $devSource->display_label }}</a>
                                        </td>
                                        <td data-test="dashboard-development-overview-development">
                                            <a
                                                href="{{ route('sources.developments.show', [$devSource, \App\Support\DevelopmentRouteSlug::encode($group['development'])]) }}"
                                                data-test="dashboard-development-overview-development-link"
                                            >{{ $group['development'] }}</a>
                                        </td>
                                        <td class="mono" data-test="dashboard-development-overview-total">{{ $group['total'] }}</td>
                                        <td class="mono">{{ $group['available'] }}</td>
                                        <td class="mono">{{ $group['reserved'] }}</td>
                                        <td class="mono">{{ $group['sold'] }}</td>
                                        @if ($showComingSoonColumn)
                                            <td class="mono">{{ $group['coming_soon'] }}</td>
                                        @endif
                                        <td class="mono">{{ $group['unknown'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-recent-runs">
                <div class="cc-card-header">
                    <h2 id="hdr-recent-runs" class="cc-card-title">@include('sources._dashboard-icon', ['name' => 'run'])<span>Recent activity</span></h2>
                    <p class="cc-card-desc">Latest 5 dataset comparison runs (newest first).</p>
                </div>
                <div class="cc-card-body">
                    @if ($recentRuns->isEmpty())
                        <div class="cc-empty">
                            <p class="cc-empty-title">No runs yet</p>
                            <p class="muted">Comparison runs will appear here after snapshots are ingested and compared.</p>
                        </div>
                    @else
                        <table class="cc-table cc-table--compact">
                            <thead>
                                <tr>
                                    <th scope="col">Run</th>
                                    <th scope="col">Source</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Finished</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentRuns as $run)
                                    @php
                                        $src = $run->source;
                                    @endphp
                                    <tr>
                                        <td class="mono">
                                            @if ($src !== null)
                                                <a href="{{ route('sources.runs.show', [$src, $run]) }}">{{ $run->id }}</a>
                                            @else
                                                {{ $run->id }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($src !== null)
                                                <a href="{{ route('sources.show', $src) }}">{{ $src->display_label }}</a>
                                            @else
                                                <span class="muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($run->status === 'completed')
                                                @include('sources._dashboard-status-badge', ['status' => 'completed', 'label' => 'Completed'])
                                            @elseif ($run->status === 'failed')
                                                @include('sources._dashboard-status-badge', ['status' => 'failed', 'label' => 'Failed'])
                                            @else
                                                @include('sources._dashboard-status-badge', ['status' => $run->status, 'label' => $run->status])
                                            @endif
                                        </td>
                                        <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($run->finished_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-recent-changes">
                <div class="cc-card-header">
                    <h2 id="hdr-recent-changes" class="cc-card-title">@include('sources._dashboard-icon', ['name' => 'change'])<span>Recent changes</span></h2>
                    <p class="cc-card-desc">
                        Latest 5 field-level plot changes across all monitored sources (newest first).
                        <a href="{{ route('changes.index') }}" data-test="dashboard-view-all-changes">View all changes</a>
                    </p>
                </div>
                <div class="cc-card-body">
                    @if ($recentChanges->isEmpty())
                        <div class="cc-empty">
                            <p class="cc-empty-title">No changes recorded</p>
                            <p class="muted">Plot field changes from dataset comparisons will appear here when detected.</p>
                        </div>
                    @else
                        <table class="cc-table cc-table--compact">
                            <thead>
                                <tr>
                                    <th scope="col">Changed at</th>
                                    <th scope="col">Source</th>
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
                                    @endphp
                                    <tr data-test="dashboard-recent-change-row">
                                        <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($change->changed_at) }}</td>
                                        <td>
                                            @if ($source !== null)
                                                <a href="{{ route('sources.show', $source) }}">{{ $source->display_label }}</a>
                                            @else
                                                <span class="muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (($change->entity_type ?? null) === 'plot' && $change->entity_id !== null && $change->entity_id !== '')
                                                <div class="cc-entity-display">
                                                    @if ($changePlotMeta !== null && $changePlotMeta['plot_label'] !== null)
                                                        <div class="cc-entity-display__primary">{{ $changePlotMeta['plot_label'] }}</div>
                                                    @endif
                                                    <div class="cc-entity-display__tech muted mono">
                                                        {{ $change->entity_type }}:{{ $change->entity_id }}
                                                    </div>
                                                </div>
                                            @else
                                                <span class="mono">{{ $entityLabel }}</span>
                                            @endif
                                        </td>
                                        <td class="mono">{{ $change->field }}</td>
                                        <td class="mono">{{ $change->old_value ?? '-' }}</td>
                                        <td class="mono">{{ $change->new_value ?? '-' }}</td>
                                        <td class="mono">
                                            @if ($source !== null && $run !== null)
                                                <a href="{{ route('sources.runs.show', [$source, $run]) }}">{{ $change->dataset_comparison_run_id }}</a>
                                            @elseif ($change->dataset_comparison_run_id !== null)
                                                {{ $change->dataset_comparison_run_id }}
                                            @else
                                                <span class="muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-recent-issues">
                <div class="cc-card-header">
                    <h2 id="hdr-recent-issues" class="cc-card-title">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Recent active issues</span></h2>
                    <p class="cc-card-desc">
                        Latest 5 open or acknowledged issues (newest first). Ignored and resolved issues stay on the
                        <a href="{{ route('issues.index') }}" data-test="dashboard-view-all-issues">Issues</a> page.
                    </p>
                </div>
                <div class="cc-card-body">
                    @if ($recentIssues->isEmpty())
                        <div class="cc-empty" data-test="dashboard-recent-issues-empty">
                            @if ($hasInactiveIssues)
                                <p class="cc-empty-title">No active issues</p>
                                <p class="muted">Ignored and resolved issues are still available on the <a href="{{ route('issues.index') }}">Issues</a> page.</p>
                            @else
                                <p class="cc-empty-title">No active issues</p>
                                <p class="muted">Open or acknowledged issues from dataset checks and comparisons will appear here when detected.</p>
                            @endif
                        </div>
                    @else
                        <table class="cc-table cc-table--compact">
                            <thead>
                                <tr>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Message</th>
                                    <th scope="col">Source</th>
                                    <th scope="col">Run</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentIssues as $issue)
                                    @php
                                        $issueSource = $issue->monitoredSource;
                                        $issueRun = $issue->datasetComparisonRun;
                                    @endphp
                                    <tr>
                                        <td>
                                            @if ($issue->severity === 'error')
                                                @include('sources._dashboard-severity-badge', ['severity' => 'error', 'label' => 'Error'])
                                            @elseif ($issue->severity === 'warning')
                                                @include('sources._dashboard-severity-badge', ['severity' => 'warning', 'label' => 'Warning'])
                                            @elseif ($issue->severity === 'info')
                                                @include('sources._dashboard-severity-badge', ['severity' => 'info', 'label' => 'Info'])
                                            @else
                                                @include('sources._dashboard-severity-badge', ['severity' => $issue->severity, 'label' => $issue->severity])
                                            @endif
                                        </td>
                                        <td>{{ $issue->message }}</td>
                                        <td>
                                            @if ($issueSource !== null)
                                                <a href="{{ route('sources.show', $issueSource) }}">{{ $issueSource->display_label }}</a>
                                            @else
                                                <span class="muted">-</span>
                                            @endif
                                        </td>
                                        <td class="mono">
                                            @if ($issueRun !== null && $issueSource !== null)
                                                <a href="{{ route('sources.runs.show', [$issueSource, $issueRun]) }}">{{ $issueRun->id }}</a>
                                            @elseif ($issueRun !== null)
                                                {{ $issueRun->id }}
                                            @else
                                                <span class="muted">-</span>
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
