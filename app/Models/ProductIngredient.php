<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductIngredient extends Model
{
    protected $fillable = ['product_id', 'item_id', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
