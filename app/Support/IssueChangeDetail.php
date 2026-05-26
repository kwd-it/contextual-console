<?php

namespace App\Support;

use App\Core\Models\DatasetIssue;
use App\Domains\Housebuilder\Services\PlotDatasetChangeLogIssueCreator;

final class IssueChangeDetail
{
    /** @var list<string> */
    private const CHANGE_DRIVEN_ISSUE_TYPES = [
        PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_STATUS_CHANGED,
        PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_PRICE_CHANGED,
        PlotDatasetChangeLogIssueCreator::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE,
    ];

    public static function isChangeDriven(DatasetIssue $issue): bool
    {
        return in_array($issue->issue_type, self::CHANGE_DRIVEN_ISSUE_TYPES, true);
    }

    /**
     * Human-readable old -> new label for change-driven issues, or null when not applicable.
     */
    public static function transitionLabel(DatasetIssue $issue): ?string
    {
        if (! self::isChangeDriven($issue)) {
            return null;
        }

        return self::transitionFromContext($issue);
    }

    /**
     * Human-readable old -> new label when context stores a transition, including non change-driven issues.
     */
    public static function transitionLabelForDisplay(DatasetIssue $issue): ?string
    {
        $changeDriven = self::transitionLabel($issue);
        if ($changeDriven !== null) {
            return $changeDriven;
        }

        return self::transitionFromContext($issue);
    }

    private static function transitionFromContext(DatasetIssue $issue): ?string
    {
        $context = is_array($issue->context) ? $issue->context : [];
        if (! array_key_exists('old_value', $context) && ! array_key_exists('new_value', $context)) {
            return null;
        }

        $field = isset($context['field']) && is_string($context['field']) && $context['field'] !== ''
            ? $context['field']
            : ($issue->field ?? null);

        $old = self::formatValue($context['old_value'] ?? null, $field);
        $new = self::formatValue($context['new_value'] ?? null, $field);

        return "{$old} -> {$new}";
    }

    private static function formatValue(mixed $value, ?string $field): string
    {
        if ($value === null) {
            return '-';
        }

        return (string) $value;
    }
}
