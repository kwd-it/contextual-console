@php
    $sevKey = strtolower((string) ($severity ?? ''));
    $sevClass = match ($sevKey) {
        'error' => 'cc-sev--error',
        'warning' => 'cc-sev--warning',
        'info' => 'cc-sev--info',
        default => 'cc-sev--default',
    };
    $iconName = match ($sevKey) {
        'error' => 'cross',
        'warning' => 'issue',
        'info' => 'info',
        default => 'dot',
    };
    $sevLabel = $label ?? ($severity ?? '');
@endphp
<span class="cc-sev {{ $sevClass }}">
    @include('sources._dashboard-icon', ['name' => $iconName, 'class' => 'cc-badge__icon'])
    <span>{{ $sevLabel }}</span>
</span>
