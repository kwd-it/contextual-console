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

        if ($development !== null) {
            return $development;
        }

        return self::fromPlotUrl($plot['url'] ?? null) ?? self::UNKNOWN_LABEL;
    }

    /**
     * @param  array<string, mixed>  $plot
     */
    public static function plotMatches(array $plot, string $developmentLabel): bool
    {
        return self::fromPlot($plot) === $developmentLabel;
    }

    private static function fromPlotUrl(mixed $url): ?string
    {
        $urlString = self::nonEmptyString($url);
        if ($urlString === null) {
            return null;
        }

        $path = parse_url($urlString, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = str_contains($urlString, '://') ? null : $urlString;
        }

        if ($path === null || $path === '') {
            return null;
        }

        if (! preg_match('#/developments/([^/]+)#', $path, $matches)) {
            return null;
        }

        return self::labelFromDevelopmentSlug(rawurldecode($matches[1]));
    }

    private static function labelFromDevelopmentSlug(string $slug): ?string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $withSpaces = preg_replace('/[-_]+/', ' ', $slug);
        if (! is_string($withSpaces)) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $withSpaces) ?? '');
        if ($normalized === '') {
            return null;
        }

        return self::titleCase($normalized);
    }

    private static function titleCase(string $value): string
    {
        return ucwords(mb_strtolower($value, 'UTF-8'), " \t\r\n\f\v");
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
