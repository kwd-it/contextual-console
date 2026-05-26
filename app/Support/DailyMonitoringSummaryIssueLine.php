<?php

namespace App\Support;

final class DailyMonitoringSummaryIssueLine
{
    public function __construct(
        public readonly string $severity,
        public readonly ?string $issueType,
        public readonly string $message,
        public readonly ?string $transition,
        public readonly string $suffix,
        public readonly ?string $issueShowUrl = null,
        public readonly ?string $runShowUrl = null,
    ) {}

    public function toPlainTextLine(): string
    {
        $label = '  - ['.$this->severity.']';

        if ($this->issueType !== null && $this->issueType !== '') {
            $label .= ' '.$this->issueType.':';
        }

        $label .= ' '.trim($this->message);

        if ($this->transition !== null) {
            $label .= ' ('.$this->transition.')';
        }

        if ($this->suffix !== '') {
            $label .= ' '.$this->suffix;
        }

        return $label;
    }
}
