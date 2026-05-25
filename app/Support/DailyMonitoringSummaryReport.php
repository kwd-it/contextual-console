<?php

namespace App\Support;

/**
 * Structured daily monitoring summary for plain-text and HTML email rendering.
 */
final class DailyMonitoringSummaryReport
{
    /**
     * @param  list<DailyMonitoringSummarySourceSection>  $sources
     */
    public function __construct(
        public readonly ?string $emptyMessage,
        public readonly string $title = 'Daily monitoring summary',
        public readonly ?string $periodLabel = null,
        public readonly array $sources = [],
    ) {}

    public function toPlainText(): string
    {
        if ($this->emptyMessage !== null) {
            return $this->emptyMessage;
        }

        $lines = [];
        $lines[] = $this->title;
        if ($this->periodLabel !== null) {
            $lines[] = $this->periodLabel;
        }
        $lines[] = '';

        foreach ($this->sources as $source) {
            $lines[] = $source->name;
            $lines[] = 'Source key: '.$source->sourceKey;
            $lines[] = sprintf(
                'Latest run in period: #%d %s',
                $source->periodRunId,
                $source->periodRunStatus,
            );
            $lines[] = '  finished '.$source->periodRunFinishedAt;

            if ($source->overallRunId !== null
                && $source->overallRunStatus !== null
                && $source->overallRunFinishedAt !== null
                && $source->overallRunId !== $source->periodRunId) {
                $lines[] = sprintf(
                    'Overall latest run: #%d %s (finished %s)',
                    $source->overallRunId,
                    $source->overallRunStatus,
                    $source->overallRunFinishedAt,
                );
            }

            if ($source->recoveryNote !== '') {
                $lines[] = $source->recoveryNote;
            }

            $lines[] = sprintf(
                'Changes: added=%d removed=%d changed=%d unchanged=%d',
                $source->added,
                $source->removed,
                $source->changed,
                $source->unchanged,
            );
            $lines[] = sprintf(
                'Active issues: %d errors=%d warnings=%d info=%d',
                $source->activeIssueCount,
                $source->errorCount,
                $source->warningCount,
                $source->infoCount,
            );

            foreach ($source->issues as $issue) {
                $lines[] = $issue->toPlainTextLine();
            }

            $lines[] = '';
        }

        return rtrim(implode(PHP_EOL, $lines));
    }
}
