<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetIssue extends Model
{
    protected $fillable = [
        'monitored_source_id',
        'dataset_snapshot_id',
        'dataset_comparison_run_id',
        'entity_type',
        'entity_id',
        'field',
        'issue_type',
        'severity',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * @return BelongsTo<MonitoredSource, $this>
     */
    public function monitoredSource(): BelongsTo
    {
        return $this->belongsTo(MonitoredSource::class, 'monitored_source_id');
    }

    /**
     * @return BelongsTo<DatasetSnapshot, $this>
     */
    public function datasetSnapshot(): BelongsTo
    {
        return $this->belongsTo(DatasetSnapshot::class, 'dataset_snapshot_id');
    }

    /**
     * @return BelongsTo<DatasetComparisonRun, $this>
     */
    public function datasetComparisonRun(): BelongsTo
    {
        return $this->belongsTo(DatasetComparisonRun::class, 'dataset_comparison_run_id');
    }
}
