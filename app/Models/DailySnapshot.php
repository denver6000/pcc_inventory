<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySnapshot extends Model
{
    protected $fillable = ['snapshot_date', 'captured_at', 'items', 'products', 'restock_batch_items', 'notes'];

    protected function casts(): array
    {
        return [
            'snapshot_date'        => 'date',
            'captured_at'          => 'datetime',
            'items'                => 'array',
            'products'             => 'array',
            'restock_batch_items'  => 'array',
        ];
    }
}
