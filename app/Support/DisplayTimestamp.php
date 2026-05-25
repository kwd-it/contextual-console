<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class DisplayTimestamp
{
    public static function timezone(): string
    {
        return (string) config('app.schedule_timezone', 'Europe/London');
    }

    public static function format(?CarbonInterface $timestamp, string $placeholder = '-'): string
    {
        if ($timestamp === null) {
            return $placeholder;
        }

        return $timestamp->copy()
            ->timezone(self::timezone())
            ->format('Y-m-d H:i:s');
    }
}
