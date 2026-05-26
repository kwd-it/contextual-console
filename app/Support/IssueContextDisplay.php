<?php

namespace App\Support;

use App\Core\Models\DatasetIssue;

final class IssueContextDisplay
{
    /** @var list<string> */
    private const SKIP_WHEN_TRANSITION_SHOWN = [
        'old_value',
        'new_value',
        'field',
    ];

    /**
     * @return list<array{key: string, value: string}>
     */
    public static function entries(DatasetIssue $issue): array
    {
        $context = is_array($issue->context) ? $issue->context : [];
        if ($context === []) {
            return [];
        }

        $skip = IssueChangeDetail::transitionLabelForDisplay($issue) !== null
            ? self::SKIP_WHEN_TRANSITION_SHOWN
            : [];

        $entries = [];
        foreach ($context as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (in_array($key, $skip, true)) {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'value' => self::formatValue($value),
            ];
        }

        usort($entries, fn (array $a, array $b) => strcmp($a['key'], $b['key']));

        return $entries;
    }

    private static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '-';
    }
}
