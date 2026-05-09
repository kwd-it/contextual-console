<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContextualConsoleDailySummaryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $summary,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Daily monitoring summary')
            ->text('emails.contextual-console.daily-summary');
    }
}
