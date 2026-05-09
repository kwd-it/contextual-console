<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'dataset_comparison_run_id',
        'field',
        'old_value',
        'new_value',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<DatasetComparisonRun, $this>
     */
    public function datasetComparisonRun(): BelongsTo
    {
        return $this->belongsTo(DatasetComparisonRun::class, 'dataset_comparison_run_id');
    }
}
