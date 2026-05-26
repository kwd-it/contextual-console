@php
    $dailySummarySubscriptionWarning = \App\Support\DailySummarySubscriptionWarning::forUi();
@endphp
@if ($dailySummarySubscriptionWarning !== null)
    <div
        class="cc-warning-banner{{ $dailySummarySubscriptionWarning['severity'] === \App\Support\DailySummarySubscriptionWarning::SEVERITY_NONE ? ' cc-warning-banner--critical' : '' }}"
        role="alert"
        data-test="daily-summary-subscription-warning"
        data-test-severity="{{ $dailySummarySubscriptionWarning['severity'] }}"
    >
        <p>{{ $dailySummarySubscriptionWarning['message'] }}</p>
    </div>
@endif
