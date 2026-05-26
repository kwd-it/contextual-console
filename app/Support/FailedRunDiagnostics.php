<?php

namespace App\Support;

use App\Core\Models\DatasetIssue;
use App\Core\Services\SourceRunFailedIssueService;

final class FailedRunDiagnostics
{
    /**
     * @param  iterable<int, DatasetIssue>  $issues
     * @return array{issue: DatasetIssue|null, exceptionMessage: string|null, issueShowUrl: string|null}|null
     */
    public static function forRun(string $runStatus, iterable $issues): ?array
    {
        if ($runStatus !== 'failed') {
            return null;
        }

        $failedIssue = self::findSourceRunFailedIssue($issues);
        $exceptionMessage = $failedIssue !== null
            ? self::exceptionMessageFromIssue($failedIssue)
            : null;
        $issueShowUrl = $failedIssue !== null
            ? route('issues.show', $failedIssue)
            : null;

        return [
            'issue' => $failedIssue,
            'exceptionMessage' => $exceptionMessage,
            'issueShowUrl' => $issueShowUrl,
        ];
    }

    /**
     * @param  iterable<int, DatasetIssue>  $issues
     */
    private static function findSourceRunFailedIssue(iterable $issues): ?DatasetIssue
    {
        foreach ($issues as $issue) {
            if ($issue instanceof DatasetIssue
                && $issue->issue_type === SourceRunFailedIssueService::ISSUE_TYPE) {
                return $issue;
            }
        }

        return null;
    }

    private static function exceptionMessageFromIssue(DatasetIssue $issue): ?string
    {
        $context = is_array($issue->context) ? $issue->context : [];
        $message = $context['exception_message'] ?? null;

        if (! is_string($message)) {
            return null;
        }

        $trimmed = trim($message);

        return $trimmed !== '' ? $trimmed : null;
    }
}
