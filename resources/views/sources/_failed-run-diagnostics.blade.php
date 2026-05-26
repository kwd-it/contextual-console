@php
    $exceptionMessage = $diagnostics['exceptionMessage'] ?? null;
    $issueShowUrl = $diagnostics['issueShowUrl'] ?? null;
@endphp

<section class="cc-card" aria-labelledby="hdr-failed-run-diagnostics" data-test="failed-run-diagnostics">
    <div class="cc-card-header">
        <h2 class="cc-card-title" id="hdr-failed-run-diagnostics">@include('sources._dashboard-icon', ['name' => 'issue'])<span>Failed run diagnostics</span></h2>
        <p class="cc-card-desc">Why this comparison run did not create a new snapshot.</p>
    </div>
    <div class="cc-card-body cc-card-body--padded">
        <p data-test="failed-run-diagnostics-summary">This run failed before a new snapshot was created.</p>
        @if ($exceptionMessage !== null)
            <div class="cc-failed-run-diagnostics__detail">
                <div class="cc-details muted">Failure detail</div>
                <pre class="cc-context-value mono" data-test="failed-run-diagnostics-exception">{{ $exceptionMessage }}</pre>
            </div>
        @endif
        @if ($issueShowUrl !== null)
            <p class="cc-failed-run-diagnostics__actions">
                <a href="{{ $issueShowUrl }}" data-test="failed-run-diagnostics-issue-link">View issue details</a>
            </p>
        @endif
    </div>
</section>
