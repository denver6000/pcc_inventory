<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'image_path', 'unit_id', 'cost_per_unit', 'default_stock', 'current_stock'];

    protected function casts(): array
    {
        return [
            'cost_per_unit' => 'float',
            'default_stock' => 'float',
            'current_stock' => 'float',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
