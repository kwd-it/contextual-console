<?php

namespace App\Mail;

use App\Support\DailyMonitoringSummaryReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class ContextualConsoleDailySummaryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public readonly string $summary;

    public function __construct(
        public readonly DailyMonitoringSummaryReport $report,
    ) {
        $this->summary = $report->toPlainText();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daily monitoring summary',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contextual-console.daily-summary-html',
            with: [
                'report' => $this->report,
            ],
        );
    }

    public function build(): void
    {
        $this->text(new HtmlString($this->summary));
    }
}
