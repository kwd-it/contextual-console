<?php

use App\Core\Models\DatasetIssue;
use App\Core\Services\SourceRunFailedIssueService;
use App\Support\FailedRunDiagnostics;
use Tests\TestCase;

uses(TestCase::class);

it('returns null for non-failed runs', function () {
    expect(FailedRunDiagnostics::forRun('completed', []))->toBeNull();
});

it('returns diagnostics for failed runs without a source_run_failed issue', function () {
    $diagnostics = FailedRunDiagnostics::forRun('failed', []);

    expect($diagnostics)->toBeArray()
        ->and($diagnostics['issue'])->toBeNull()
        ->and($diagnostics['exceptionMessage'])->toBeNull()
        ->and($diagnostics['issueShowUrl'])->toBeNull();
});

it('extracts exception_message and issue link from source_run_failed issues', function () {
    $issue = DatasetIssue::make([
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'context' => [
            'exception_message' => "Missing required auth header env value for key 'HB_PLOT_TOKEN'.",
        ],
    ]);
    $issue->id = 42;
    $issue->exists = true;

    $diagnostics = FailedRunDiagnostics::forRun('failed', [$issue]);

    expect($diagnostics)->not->toBeNull()
        ->and($diagnostics['issue'])->toBe($issue)
        ->and($diagnostics['exceptionMessage'])->toBe("Missing required auth header env value for key 'HB_PLOT_TOKEN'.")
        ->and($diagnostics['issueShowUrl'])->toBe(route('issues.show', $issue));
});

it('ignores blank exception_message values', function () {
    $issue = DatasetIssue::make([
        'issue_type' => SourceRunFailedIssueService::ISSUE_TYPE,
        'context' => ['exception_message' => '   '],
    ]);
    $issue->id = 7;
    $issue->exists = true;

    $diagnostics = FailedRunDiagnostics::forRun('failed', [$issue]);

    expect($diagnostics['exceptionMessage'])->toBeNull()
        ->and($diagnostics['issueShowUrl'])->toBe(route('issues.show', $issue));
});
