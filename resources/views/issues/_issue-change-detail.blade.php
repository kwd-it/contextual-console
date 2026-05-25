@php
    $issueChangeTransition = \App\Support\IssueChangeDetail::transitionLabel($issue);
@endphp
@if ($issueChangeTransition !== null)
    <div class="cc-details muted mono" data-test="issue-change-detail">{{ $issueChangeTransition }}</div>
@endif
