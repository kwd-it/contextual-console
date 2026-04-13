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
        return $this->write(class_basename($model), $model->id, $field, $old, $new);
    }

    public function recordPlotPrice(int|string $plotId, mixed $oldPrice, mixed $newPrice): ChangeLog
    {
        return $this->write('plot', $plotId, 'price', $oldPrice, $newPrice);
    }

    private function write(string $entityType, int|string $entityId, string $field, mixed $old, mixed $new): ChangeLog
    {
        return ChangeLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'changed_at' => now(),
        ]);
    }
}
