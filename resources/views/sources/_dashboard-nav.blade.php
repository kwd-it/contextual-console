@php
    $routeName = request()->route()?->getName();
    $dashboardActive = $routeName === 'dashboard.index';
    $sourcesActive = in_array($routeName, ['sources.index', 'sources.show', 'sources.runs.show', 'sources.developments.show'], true);
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
        ><span class="cc-icon-label">@include('sources._dashboard-icon', ['name' => 'dashboard'])<span>Dashboard</span></span></a>
        <a
            href="{{ route('sources.index') }}"
            class="cc-nav__link {{ $sourcesActive ? 'cc-nav__link--current' : '' }}"
            @if ($sourcesActive) aria-current="page" @endif
        ><span class="cc-icon-label">@include('sources._dashboard-icon', ['name' => 'source'])<span>Sources</span></span></a>
        <a
            href="{{ route('changes.index') }}"
            class="cc-nav__link {{ $changesActive ? 'cc-nav__link--current' : '' }}"
            @if ($changesActive) aria-current="page" @endif
        ><span class="cc-icon-label">@include('sources._dashboard-icon', ['name' => 'change'])<span>Changes</span></span></a>
        <a
            href="{{ route('issues.index') }}"
            class="cc-nav__link {{ $issuesActive ? 'cc-nav__link--current' : '' }}"
            @if ($issuesActive) aria-current="page" @endif
        ><span class="cc-icon-label">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Issues</span></span></a>
        <form method="post" action="{{ route('logout') }}" class="cc-nav__logout-form">
            @csrf
            <button type="submit" class="cc-nav__link cc-nav__logout" data-test="logout"><span class="cc-icon-label">@include('sources._dashboard-icon', ['name' => 'logout'])<span>Log out</span></span></button>
        </form>
    </nav>
</div>
