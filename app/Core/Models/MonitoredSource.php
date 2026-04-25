<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredSource extends Model
{
    protected $fillable = [
        'key',
        'name',
        'endpoint_url',
        'auth_header_name',
        'auth_token_env_key',
    ];

    /**
     * @return HasMany<DatasetSnapshot, $this>
     */
    public function datasetSnapshots(): HasMany
    {
        return $this->hasMany(DatasetSnapshot::class, 'source_id');
    }

    /**
     * @return HasMany<DatasetComparisonRun, $this>
     */
    public function datasetComparisonRuns(): HasMany
    {
        return $this->hasMany(DatasetComparisonRun::class, 'source_id');
    }
}

