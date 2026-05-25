<?php

namespace App\Console\Commands;

use App\Mail\ContextualConsoleDailySummaryMail;
use App\Support\DailyMonitoringSummaryBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DailySummaryCommand extends Command
{
    protected $signature = 'contextual-console:daily-summary
        {--hours=24 : Look back this many hours for runs}
        {--email : Email the summary to the configured recipient}';

    protected $description = 'Summarise recent monitoring results by monitored source (for future notifications).';

    public function handle(DailyMonitoringSummaryBuilder $builder): int
    {
        $hours = (int) $this->option('hours');
        if ($hours <= 0) {
            $this->error('--hours must be a positive integer.');

            return self::FAILURE;
        }

        try {
            $summary = $builder->build($hours);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($summary);

        if ((bool) $this->option('email')) {
            $to = (string) config('contextual_console.daily_summary_to', '');
            if (trim($to) === '') {
                $this->error('Daily summary email requested, but no recipient is configured. Set CONTEXTUAL_CONSOLE_DAILY_SUMMARY_TO.');

                return self::FAILURE;
            }

            Mail::send((new ContextualConsoleDailySummaryMail($summary))->to($to));
        }

        return self::SUCCESS;
    }
}
