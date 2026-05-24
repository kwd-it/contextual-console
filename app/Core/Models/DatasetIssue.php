<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetIssue extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_RESOLVED = 'resolved';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ACKNOWLEDGED,
        self::STATUS_IGNORED,
        self::STATUS_RESOLVED,
    ];

    protected $fillable = [
        'monitored_source_id',
        'dataset_snapshot_id',
        'dataset_comparison_run_id',
        'entity_type',
        'entity_id',
        'field',
        'issue_type',
        'severity',
        'status',
        'message',
        'context',
    ];

    protected $attributes = [
        'status' => self::STATUS_OPEN,
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
