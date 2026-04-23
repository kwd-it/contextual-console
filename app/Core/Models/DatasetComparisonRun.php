<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetComparisonRun extends Model
{
    protected $fillable = [
        'source_id',
        'current_snapshot_id',
        'previous_snapshot_id',
        'status',
        'summary',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MonitoredSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MonitoredSource::class, 'source_id');
    }

    /**
     * @return BelongsTo<DatasetSnapshot, $this>
     */
    public function currentSnapshot(): BelongsTo
    {
        return $this->belongsTo(DatasetSnapshot::class, 'current_snapshot_id');
    }

    /**
     * @return BelongsTo<DatasetSnapshot, $this>
     */
    public function previousSnapshot(): BelongsTo
    {
        return $this->belongsTo(DatasetSnapshot::class, 'previous_snapshot_id');
    }
}

