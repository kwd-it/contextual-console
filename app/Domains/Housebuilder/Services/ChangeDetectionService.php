<?php

namespace App\Domains\Housebuilder\Services;

use App\Core\Models\ChangeLog;

class ChangeDetectionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function record($model, $field, $old, $new)
    {
        return ChangeLog::create([
            'entity_type' => class_basename($model),
            'entity_id' => $model->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'changed_at' => now(),
        ]);
    }
}
