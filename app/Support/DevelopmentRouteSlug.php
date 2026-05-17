<?php

namespace App\Support;

/**
 * URL-safe encoding for development names in routes (stable slug for unknown developments).
 */
final class DevelopmentRouteSlug
{
    private const string UNKNOWN_SLUG = '_unknown-development';

    public static function encode(string $developmentLabel): string
    {
        if ($developmentLabel === PlotDevelopmentLabel::UNKNOWN_LABEL) {
            return self::UNKNOWN_SLUG;
        }

        return rawurlencode($developmentLabel);
    }

    public static function decode(string $slug): string
    {
        if ($slug === self::UNKNOWN_SLUG) {
            return PlotDevelopmentLabel::UNKNOWN_LABEL;
        }

        return rawurldecode($slug);
    }
}
