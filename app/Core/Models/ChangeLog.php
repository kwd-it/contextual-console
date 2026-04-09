<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeLog extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'field',
        'old_value',
        'new_value',
        'changed_at',
    ];
}