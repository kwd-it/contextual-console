@php
    $healthKey = strtolower((string) ($healthKey ?? ''));
    $badgeClass = 'cc-badge--neutral';
    $iconName = 'dot';

    if ($healthKey === 'healthy') {
        $badgeClass = 'cc-badge--ok';
        $iconName = 'check';
    } elseif ($healthKey === 'failing') {
        $badgeClass = 'cc-badge--fail';
        $iconName = 'cross';
    } elseif ($healthKey === 'needs_review') {
        $badgeClass = 'cc-badge--warn';
        $iconName = 'dot';
    }

    $badgeLabel = $label ?? '';
@endphp
<span class="cc-badge {{ $badgeClass }}" data-test="source-health-badge">
    @include('sources._dashboard-icon', ['name' => $iconName, 'class' => 'cc-badge__icon'])
    <span>{{ $badgeLabel }}</span>
</span>
