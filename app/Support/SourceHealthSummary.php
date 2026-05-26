<?php

namespace App\Support;

final class SourceHealthSummary
{
    public const KEY_NOT_RUN_YET = 'not_run_yet';

    public const KEY_FAILING = 'failing';

    public const KEY_NEEDS_REVIEW = 'needs_review';

    public const KEY_HEALTHY = 'healthy';

    /**
     * @param  array{
     *   latest_run_id?: int|null,
     *   latest_run_status?: string|null,
     *   error_count?: int,
     *   warning_count?: int
     * }  $summary
     * @return array{key: string, label: string}
     */
    public static function forSummary(array $summary): array
    {
        if (($summary['latest_run_id'] ?? null) === null) {
            return [
                'key' => self::KEY_NOT_RUN_YET,
                'label' => 'Not run yet',
            ];
        }

        $runStatus = strtolower((string) ($summary['latest_run_status'] ?? ''));

        if ($runStatus === 'failed') {
            return [
                'key' => self::KEY_FAILING,
                'label' => 'Failing',
            ];
        }

        if (in_array($runStatus, ['completed', 'baseline'], true)) {
            $errors = (int) ($summary['error_count'] ?? 0);
            $warnings = (int) ($summary['warning_count'] ?? 0);

            if ($errors > 0 || $warnings > 0) {
                return [
                    'key' => self::KEY_NEEDS_REVIEW,
                    'label' => 'Needs review',
                ];
            }

            return [
                'key' => self::KEY_HEALTHY,
                'label' => 'Healthy',
            ];
        }

        return [
            'key' => self::KEY_NEEDS_REVIEW,
            'label' => 'Needs review',
        ];
    }
}
