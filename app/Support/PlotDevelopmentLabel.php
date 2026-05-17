<?php

namespace App\Support;

/**
 * Resolves development labels from snapshot plot rows (dashboard grouping and drilldown).
 */
final class PlotDevelopmentLabel
{
    public const string UNKNOWN_LABEL = 'Unknown development';

    /**
     * @param  array<string, mixed>  $plot
     */
    public static function fromPlot(array $plot): string
    {
        $development = self::nonEmptyString($plot['development'] ?? null)
            ?? self::nonEmptyString($plot['development_name'] ?? null);

        return $development ?? self::UNKNOWN_LABEL;
    }

    /**
     * @param  array<string, mixed>  $plot
     */
    public static function plotMatches(array $plot, string $developmentLabel): bool
    {
        return self::fromPlot($plot) === $developmentLabel;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return html_entity_decode($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
