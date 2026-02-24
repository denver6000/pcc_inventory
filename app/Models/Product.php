<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'image_path', 'selling_price'];

    protected function casts(): array
    {
        return [
            'selling_price' => 'float',
        ];
    }

    public function getComputedCostAttribute(): float
    {
        if (!$this->relationLoaded('ingredients')) return 0.0;
        return round(
            $this->ingredients->sum(function ($ing) {
                if (!$ing->relationLoaded('item')) return 0.0;
                return (float) $ing->quantity * (float) ($ing->item->cost_per_unit ?? 0);
            }),
            4
        );
    }

    public function ingredients()
    {
        return $this->hasMany(ProductIngredient::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
