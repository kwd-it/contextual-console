<?php

namespace App\Domains\Housebuilder\Services;

use App\Core\Models\ChangeLog;
use App\Core\Models\DatasetComparisonRun;
use App\Core\Models\DatasetIssue;

class PlotDatasetChangeLogIssueCreator
{
    public const ENTITY_TYPE = 'plot';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';

    public const ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE = 'plot_removed_from_source';
    public const ISSUE_TYPE_PLOT_STATUS_CHANGED = 'plot_status_changed';
    public const ISSUE_TYPE_PLOT_PRICE_CHANGED = 'plot_price_changed';

    public function createForRun(DatasetComparisonRun $run): void
    {
        $logs = ChangeLog::query()
            ->where('dataset_comparison_run_id', $run->id)
            ->where('entity_type', self::ENTITY_TYPE)
            ->whereIn('field', ['presence', 'status', 'price'])
            ->orderBy('id')
            ->get();

        foreach ($logs as $log) {
            $issue = $this->issueFromLog($log);

            if ($issue === null) {
                continue;
            }

            $exists = DatasetIssue::query()
                ->where('dataset_comparison_run_id', $run->id)
                ->where('entity_type', $log->entity_type)
                ->where('entity_id', (string) $log->entity_id)
                ->where('field', $log->field)
                ->where('issue_type', $issue['issue_type'])
                ->where('context->change_log_id', $log->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DatasetIssue::create([
                'monitored_source_id' => $run->source_id,
                'dataset_snapshot_id' => $run->current_snapshot_id,
                'dataset_comparison_run_id' => $run->id,
                'entity_type' => $log->entity_type,
                'entity_id' => (string) $log->entity_id,
                'field' => $log->field,
                'issue_type' => $issue['issue_type'],
                'severity' => $issue['severity'],
                'message' => $issue['message'],
                'context' => [
                    'change_log_id' => $log->id,
                    'old_value' => $log->old_value,
                    'new_value' => $log->new_value,
                ],
            ]);
        }
    }

    /**
     * @return array{issue_type: string, severity: string, message: string}|null
     */
    private function issueFromLog(ChangeLog $log): ?array
    {
        if ($log->field === 'presence' && $log->new_value === null) {
            return [
                'issue_type' => self::ISSUE_TYPE_PLOT_REMOVED_FROM_SOURCE,
                'severity' => self::SEVERITY_WARNING,
                'message' => 'Plot removed from source.',
            ];
        }

        if ($log->field === 'status') {
            return [
                'issue_type' => self::ISSUE_TYPE_PLOT_STATUS_CHANGED,
                'severity' => self::SEVERITY_INFO,
                'message' => 'Plot status changed.',
            ];
        }

        if ($log->field === 'price') {
            return [
                'issue_type' => self::ISSUE_TYPE_PLOT_PRICE_CHANGED,
                'severity' => self::SEVERITY_INFO,
                'message' => 'Plot price changed.',
            ];
        }

        return null;
    }
}
