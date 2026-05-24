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
                <h1 class="cc-page-title">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Issues</span></h1>
                <p class="cc-page-sub">Recent issues from daily dataset comparisons across all sources (newest first, up to {{ $issueLimit }} entries).</p>
            </header>

            <section class="cc-card" aria-labelledby="hdr-all-issues">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-all-issues">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Issue list</span></h2>
                    <p class="cc-card-desc">Validation and ingest messages recorded when comparison runs finish. Mark issues as acknowledged, ignored, or resolved as you review them; newly detected issues start as open.</p>
                </div>
                @if (session('status'))
                    <p class="cc-flash" role="status">{{ session('status') }}</p>
                @endif
                <form class="cc-filter-form cc-filter-form--issues" method="get" action="{{ route('issues.index') }}" aria-label="Filter issues">
                    <div class="cc-filter-form__fields">
                        <label>
                            Source
                            <select name="source">
                                <option value="">All sources</option>
                                @foreach ($sources as $s)
                                    <option value="{{ $s->id }}" @selected($filters['source_id'] === $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Review status
                            <select name="issue_status">
                                <option value="">All statuses</option>
                                @foreach ($issueStatusOptions as $opt)
                                    <option value="{{ $opt }}" @selected($filters['issue_status'] === $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Severity
                            <select name="severity">
                                <option value="">All severities</option>
                                @foreach ($severityOptions as $opt)
                                    <option value="{{ $opt }}" @selected($filters['severity'] === $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Date from
                            <input type="date" name="date_from" value="{{ $filters['date_from_input'] ?? '' }}">
                        </label>
                        <label>
                            Date to
                            <input type="date" name="date_to" value="{{ $filters['date_to_input'] ?? '' }}">
                        </label>
                    </div>
                    <div class="cc-filter-form__actions">
                        <button type="submit">Apply filters</button>
                        <a class="cc-filter-form__clear" href="{{ route('issues.index') }}">Clear filters</a>
                    </div>
                </form>
                <div class="cc-card-body">
                    @if ($issues->isEmpty())
                        <p class="muted cc-empty">
                            @if ($filtersActive)
                                No issues match the current filters.
                            @else
                                No issues recorded yet.
                            @endif
                        </p>
                    @else
                        <table class="cc-table">
                            <thead>
                                <tr>
                                    <th>Severity</th>
                                    <th>Source</th>
                                    <th>Run</th>
                                    <th>Entity</th>
                                    <th>Message</th>
                                    <th>Recorded</th>
                                    <th>Review status</th>
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

                                        $recordedAt = $issue->created_at?->toDateTimeString() ?? '-';

                                        $messageMetaParts = [];
                                        if (! empty($issue->issue_type)) {
                                            $messageMetaParts[] = $issue->issue_type;
                                        }
                                        if (! empty($issue->field)) {
                                            $messageMetaParts[] = 'field: '.$issue->field;
                                        }
                                    @endphp
                                    <tr>
                                        <td class="mono">
                                            @include('sources._dashboard-severity-badge', ['severity' => $issue->severity, 'label' => $issue->severity])
                                        </td>
                                        <td>
                                            @if ($source !== null)
                                                <a href="{{ route('sources.show', $source) }}">{{ $source->name }}</a>
                                            @else
                                                <span class="muted">—</span>
                                            @endif
                                            <div class="cc-details muted mono">Issue id: {{ $issue->id }}</div>
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
                                        <td>
                                            {{ $issue->message }}
                                            @if ($messageMetaParts !== [])
                                                <div class="cc-details muted mono">{{ implode(' · ', $messageMetaParts) }}</div>
                                            @endif
                                        </td>
                                        <td class="mono cc-time">{{ $recordedAt }}</td>
                                        <td>
                                            <form class="cc-issue-status-form" method="post" action="{{ route('issues.update-status', $issue) }}">
                                                @csrf
                                                @method('PATCH')
                                                @if ($filters['source_id'] !== null)
                                                    <input type="hidden" name="source" value="{{ $filters['source_id'] }}">
                                                @endif
                                                @if ($filters['issue_status'] !== null)
                                                    <input type="hidden" name="issue_status" value="{{ $filters['issue_status'] }}">
                                                @endif
                                                @if ($filters['severity'] !== null)
                                                    <input type="hidden" name="severity" value="{{ $filters['severity'] }}">
                                                @endif
                                                @if ($filters['issue_type'] !== null)
                                                    <input type="hidden" name="issue_type" value="{{ $filters['issue_type'] }}">
                                                @endif
                                                @if ($filters['date_from_input'] !== null)
                                                    <input type="hidden" name="date_from" value="{{ $filters['date_from_input'] }}">
                                                @endif
                                                @if ($filters['date_to_input'] !== null)
                                                    <input type="hidden" name="date_to" value="{{ $filters['date_to_input'] }}">
                                                @endif
                                                <label class="cc-issue-status-form__label">
                                                    <span class="visually-hidden">Review status for issue {{ $issue->id }}</span>
                                                    <select name="status" aria-label="Review status">
                                                        @foreach ($issueStatusOptions as $opt)
                                                            <option value="{{ $opt }}" @selected($issue->status === $opt)>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <button type="submit">Save</button>
                                            </form>
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
