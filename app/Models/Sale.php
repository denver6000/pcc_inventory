<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['product_id', 'quantity_sold', 'unit_price', 'total_price', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity_sold' => 'float',
            'unit_price'    => 'float',
            'total_price'   => 'float',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
