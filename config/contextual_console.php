<?php

return [
    'daily_summary_to' => env('CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO'),

    'backup' => [
        'disk' => env('CONTEXTUAL_CONSOLE_BACKUP_DISK', 's3'),
        'path' => trim((string) env('CONTEXTUAL_CONSOLE_BACKUP_PATH', 'database'), '/'),
        'retention_days' => max(0, (int) env('CONTEXTUAL_CONSOLE_BACKUP_RETENTION_DAYS', 30)),
    ],
];
