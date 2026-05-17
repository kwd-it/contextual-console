@php
    $statusKey = strtolower((string) ($status ?? ''));
    $badgeClass = 'cc-badge--neutral';
    $iconName = 'dot';

    if ($statusKey === 'completed') {
        $badgeClass = 'cc-badge--ok';
        $iconName = 'check';
    } elseif ($statusKey === 'failed') {
        $badgeClass = 'cc-badge--fail';
        $iconName = 'cross';
    } elseif (in_array($statusKey, ['running', 'pending'], true)) {
        $badgeClass = 'cc-badge--info';
        $iconName = 'run';
    }

    $badgeLabel = $label ?? ($status ?? '');
@endphp
<span class="cc-badge {{ $badgeClass }}">
    @include('sources._dashboard-icon', ['name' => $iconName, 'class' => 'cc-badge__icon'])
    <span>{{ $badgeLabel }}</span>
</span>
