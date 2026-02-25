<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockBatchItem extends Model
{
    protected $fillable = ['restock_batch_id', 'item_id', 'quantity_added', 'cost_per_unit', 'subtotal', 'journal_line_id'];

    protected function casts(): array
    {
        return [
            'quantity_added' => 'float',
            'cost_per_unit'  => 'float',
            'subtotal'       => 'float',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function batch()
    {
        return $this->belongsTo(RestockBatch::class, 'restock_batch_id');
    }
}
