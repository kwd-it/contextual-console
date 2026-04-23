<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetSnapshot extends Model
{
    protected $fillable = [
        'source_id',
        'payload',
        'captured_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'captured_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MonitoredSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MonitoredSource::class, 'source_id');
    }
}

