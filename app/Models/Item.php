<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ['name', 'image_path', 'unit_id', 'default_stock', 'current_stock'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
