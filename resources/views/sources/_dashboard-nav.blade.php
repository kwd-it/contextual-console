@php
    $routeName = request()->route()?->getName();
    $dashboardActive = $routeName === 'dashboard.index';
    $sourcesActive = in_array($routeName, ['sources.index', 'sources.show', 'sources.runs.show', 'sources.developments.show'], true);
    $changesActive = $routeName === 'changes.index';
    $issuesActive = $routeName === 'issues.index';
    $profileActive = in_array($routeName, ['profile.edit', 'profile.update'], true);
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
        <a
            href="{{ route('profile.edit') }}"
            class="cc-nav__link {{ $profileActive ? 'cc-nav__link--current' : '' }}"
            @if ($profileActive) aria-current="page" @endif
            data-test="nav-profile"
        ><span>Profile</span></a>
        <label class="cc-theme" for="cc-theme-select">
            <span class="cc-theme__label" id="cc-theme-label">Theme</span>
            <select
                id="cc-theme-select"
                class="cc-theme__select"
                data-test="theme-select"
                aria-labelledby="cc-theme-label"
            >
                <option value="system">System</option>
                <option value="light">Light</option>
                <option value="dark">Dark</option>
            </select>
        </label>
        <form method="post" action="{{ route('logout') }}" class="cc-nav__logout-form">
            @csrf
            <button type="submit" class="cc-nav__link cc-nav__logout" data-test="logout"><span class="cc-icon-label">@include('sources._dashboard-icon', ['name' => 'logout'])<span>Log out</span></span></button>
        </form>
    </nav>
</div>
<script>
    (function () {
        var storageKey = 'cc-theme';
        var select = document.getElementById('cc-theme-select');
        if (!select) {
            return;
        }

        var stored = localStorage.getItem(storageKey);
        var theme = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system';
        select.value = theme;

        function applyTheme(value) {
            var root = document.documentElement;

            if (value === 'light' || value === 'dark') {
                root.dataset.theme = value;
            } else {
                root.removeAttribute('data-theme');
            }
        }

        select.addEventListener('change', function () {
            var value = select.value;
            applyTheme(value);
            localStorage.setItem(storageKey, value);
        });
    })();
</script>
