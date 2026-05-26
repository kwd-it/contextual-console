<?php

namespace App\Support;

use App\Models\User;

final class DailySummarySubscriptionWarning
{
    public const SEVERITY_FALLBACK = 'fallback';

    public const SEVERITY_NONE = 'none';

    public static function hasSubscribers(): bool
    {
        return User::query()
            ->where('daily_summary_enabled', true)
            ->exists();
    }

    public static function hasFallbackRecipient(): bool
    {
        return trim((string) config('contextual_console.daily_summary_to', '')) !== '';
    }

    /**
     * Operator-facing warning when no user accounts are subscribed.
     *
     * @return array{message: string, severity: string}|null
     */
    public static function forUi(): ?array
    {
        if (self::hasSubscribers()) {
            return null;
        }

        if (self::hasFallbackRecipient()) {
            return [
                'message' => 'No user account is subscribed to daily summaries. Emails can still use the fallback recipient, but at least one operator should normally opt in from Profile.',
                'severity' => self::SEVERITY_FALLBACK,
            ];
        }

        return [
            'message' => 'No user account is subscribed to daily summaries and no fallback recipient is configured. Scheduled summary emails will not be sent.',
            'severity' => self::SEVERITY_NONE,
        ];
    }
}
