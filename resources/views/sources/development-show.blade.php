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
        </div>
    </body>
</html>
