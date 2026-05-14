@php
    $routeName = request()->route()?->getName();
    $dashboardActive = $routeName === 'dashboard.index';
    $sourcesActive = in_array($routeName, ['sources.index', 'sources.show', 'sources.runs.show'], true);
    $changesActive = $routeName === 'changes.index';
    $issuesActive = $routeName === 'issues.index';
@endphp
<div class="cc-app-bar">
    <div class="cc-brand">
        <a href="{{ route('dashboard.index') }}" class="cc-brand__link">Contextual Console</a>
        <span class="cc-brand__tagline muted">Website data monitoring</span>
    </div>
    <nav class="cc-nav" aria-label="Primary">
        <a
            href="{{ route('dashboard.index') }}"
            class="cc-nav__link {{ $dashboardActive ? 'cc-nav__link--current' : '' }}"
            @if ($dashboardActive) aria-current="page" @endif
        >Dashboard</a>
        <a
            href="{{ route('sources.index') }}"
            class="cc-nav__link {{ $sourcesActive ? 'cc-nav__link--current' : '' }}"
            @if ($sourcesActive) aria-current="page" @endif
        >Sources</a>
        <a
            href="{{ route('changes.index') }}"
            class="cc-nav__link {{ $changesActive ? 'cc-nav__link--current' : '' }}"
            @if ($changesActive) aria-current="page" @endif
        >Changes</a>
        <a
            href="{{ route('issues.index') }}"
            class="cc-nav__link {{ $issuesActive ? 'cc-nav__link--current' : '' }}"
            @if ($issuesActive) aria-current="page" @endif
        >Issues</a>
    </nav>
</div>
