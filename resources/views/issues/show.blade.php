<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Contextual Console - Issue #{{ $issue->id }}</title>
        @include('sources._dashboard-styles')
    </head>
    <body>
        <div class="cc-page">
            @include('sources._dashboard-nav')

            <div class="muted cc-back">
                <a href="{{ route('issues.index') }}">Back to issues</a>
            </div>

            @php
                $source = $issue->monitoredSource;
                $run = $issue->datasetComparisonRun;
                $typeExplanation = \App\Support\IssueTypeExplanation::forIssue($issue);
                $suggestedCheck = \App\Support\IssueTypeSuggestedCheck::forIssue($issue);
                $transition = \App\Support\IssueChangeDetail::transitionLabelForDisplay($issue);
                $contextEntries = \App\Support\IssueContextDisplay::entries($issue);

                $entityLabel = '-';
                if (! empty($issue->entity_type) && $issue->entity_id !== null) {
                    $entityLabel = "{$issue->entity_type}:{$issue->entity_id}";
                } elseif (! empty($issue->entity_type)) {
                    $entityLabel = (string) $issue->entity_type;
                }

                $developmentName = $plotMeta['development'] ?? null;
                $developmentHref = null;
                if ($source !== null && is_string($developmentName) && $developmentName !== '') {
                    $developmentHref = route('sources.developments.show', [
                        $source,
                        \App\Support\DevelopmentRouteSlug::encode($developmentName),
                    ]);
                }
            @endphp

            <header class="cc-page-header">
                <h1 class="cc-page-title" data-test="issue-show-title">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Issue #{{ $issue->id }}</span></h1>
                <p class="cc-page-sub">{{ $issue->message }}</p>
            </header>

            @if (session('status'))
                <p class="cc-flash" role="status">{{ session('status') }}</p>
            @endif

            <section class="cc-card" aria-labelledby="hdr-issue-summary">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-issue-summary">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Summary</span></h2>
                    <p class="cc-card-desc">Severity, review status, source, and when this issue was recorded.</p>
                </div>
                <div class="cc-card-body">
                    <table class="cc-kv">
                        <tbody>
                            <tr>
                                <th>Severity</th>
                                <td>@include('sources._dashboard-severity-badge', ['severity' => $issue->severity, 'label' => $issue->severity])</td>
                            </tr>
                            <tr>
                                <th>Review status</th>
                                <td>{{ ucfirst($issue->status) }}</td>
                            </tr>
                            <tr>
                                <th>Source</th>
                                <td>
                                    @if ($source !== null)
                                        <a href="{{ route('sources.show', $source) }}">{{ $source->display_label }}</a>
                                        <div class="cc-details muted mono">Key: {{ $source->key }}</div>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Comparison run</th>
                                <td>
                                    @if ($source !== null && $run !== null)
                                        <a href="{{ route('sources.runs.show', [$source, $run]) }}">Run #{{ $run->id }}</a>
                                    @elseif ($run !== null)
                                        <span class="mono">Run #{{ $run->id }}</span>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($issue->dataset_snapshot_id !== null)
                                <tr>
                                    <th>Snapshot id</th>
                                    <td class="mono">{{ $issue->dataset_snapshot_id }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th>Recorded at</th>
                                <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($issue->created_at) }}</td>
                            </tr>
                            <tr>
                                <th>Last updated</th>
                                <td class="mono cc-time">{{ \App\Support\DisplayTimestamp::format($issue->updated_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="cc-card" aria-labelledby="hdr-issue-details">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-issue-details">@include('sources._dashboard-icon', ['name' => 'issue'])<span>What happened</span></h2>
                    <p class="cc-card-desc">Issue type, field, plot or entity context, and any value change.</p>
                </div>
                <div class="cc-card-body">
                    <table class="cc-kv">
                        <tbody>
                            @if (! empty($issue->issue_type))
                                <tr>
                                    <th>Issue type</th>
                                    <td class="mono">{{ $issue->issue_type }}</td>
                                </tr>
                            @endif
                            @if ($typeExplanation !== null)
                                <tr>
                                    <th>Explanation</th>
                                    <td data-test="issue-type-explanation">{{ $typeExplanation }}</td>
                                </tr>
                            @endif
                            @if ($suggestedCheck !== null)
                                <tr>
                                    <th>What to check next</th>
                                    <td data-test="issue-suggested-check">{{ $suggestedCheck }}</td>
                                </tr>
                            @endif
                            @if (! empty($issue->field))
                                <tr>
                                    <th>Field</th>
                                    <td class="mono">{{ $issue->field }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th>Entity</th>
                                <td>
                                    @if (($issue->entity_type ?? null) === 'plot' && $issue->entity_id !== null && $issue->entity_id !== '')
                                        <div class="cc-entity-display">
                                            @if ($plotMeta !== null && $plotMeta['plot_label'] !== null)
                                                <div class="cc-entity-display__primary">{{ $plotMeta['plot_label'] }}</div>
                                            @endif
                                            @if ($developmentName !== null)
                                                <div class="cc-entity-display__secondary muted">
                                                    @if ($developmentHref !== null)
                                                        <a href="{{ $developmentHref }}">{{ $developmentName }}</a>
                                                    @else
                                                        {{ $developmentName }}
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($plotMeta !== null && $plotMeta['last_modified_by'] !== null)
                                                <div class="cc-entity-display__secondary muted">Last modified by: {{ $plotMeta['last_modified_by'] }}</div>
                                            @endif
                                            <div class="cc-entity-display__tech muted mono">Technical ID: {{ $issue->entity_type }}:{{ $issue->entity_id }}</div>
                                        </div>
                                    @else
                                        <span class="mono">{{ $entityLabel }}</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($transition !== null)
                                <tr>
                                    <th>Value change</th>
                                    <td class="mono" data-test="issue-change-detail">{{ $transition }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($contextEntries !== [])
                <section class="cc-card" aria-labelledby="hdr-issue-context">
                    <div class="cc-card-header">
                        <h2 class="cc-card-title" id="hdr-issue-context">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Additional context</span></h2>
                        <p class="cc-card-desc">Extra details stored with this issue for investigation.</p>
                    </div>
                    <div class="cc-card-body">
                        <table class="cc-kv" data-test="issue-context-table">
                            <tbody>
                                @foreach ($contextEntries as $entry)
                                    <tr>
                                        <th>{{ $entry['key'] }}</th>
                                        <td class="mono"><pre class="cc-context-value">{{ $entry['value'] }}</pre></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section class="cc-card" aria-labelledby="hdr-issue-review">
                <div class="cc-card-header">
                    <h2 class="cc-card-title" id="hdr-issue-review">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Review</span></h2>
                    <p class="cc-card-desc">Update review status after you have checked this issue.</p>
                </div>
                <form
                    class="cc-issue-review-form"
                    method="post"
                    action="{{ route('issues.update-status', $issue) }}"
                    aria-label="Update issue review status"
                    data-test="issue-show-status-form"
                >
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_to" value="show">
                    <div class="cc-card-body">
                        <table class="cc-kv">
                            <tbody>
                                <tr>
                                    <th scope="row">Review status</th>
                                    <td>
                                        <div class="cc-issue-review-form__controls">
                                            <select name="status" aria-label="Review status">
                                                @foreach ($issueStatusOptions as $opt)
                                                    <option value="{{ $opt }}" @selected($issue->status === $opt)>{{ ucfirst($opt) }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit">Save status</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </form>
            </section>
        </div>
    </body>
</html>
