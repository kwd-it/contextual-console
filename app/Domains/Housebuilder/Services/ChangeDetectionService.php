<?php

namespace App\Domains\Housebuilder\Services;

use App\Core\Models\ChangeLog;

/**
 * Persists field-level changes to {@see ChangeLog}.
 *
 * Detector-driven logs should use stable domain `entity_type` values (for example `plot`)
 * via {@see self::recordDomainField()} or a narrow helper such as {@see self::recordPlotPrice()},
 * not {@see self::record()} with stdClass or other ad-hoc objects (that yields unstable types like `stdClass`).
 *
 * {@see self::record()} is for Eloquent models: `entity_type` is the short class name (`User`, `Plot`, …)
 * and `entity_id` is the model primary key.
 */
class ChangeDetectionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model|object{id: int|string}  $model
     */
    public function record($model, $field, $old, $new)
    {
        return $this->write(class_basename($model), $model->id, $field, $old, $new);
    }

    /**
     * @param  int|string  $entityId  Stable domain identifier (for plots: the dataset `id` field).
     */
    public function recordDomainField(string $entityType, int|string $entityId, string $field, mixed $old, mixed $new): ChangeLog
    {
        return $this->write($entityType, $entityId, $field, $old, $new);
    }

    public function recordPlotPrice(int|string $plotId, mixed $oldPrice, mixed $newPrice): ChangeLog
    {
        return $this->recordDomainField('plot', $plotId, 'price', $oldPrice, $newPrice);
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
