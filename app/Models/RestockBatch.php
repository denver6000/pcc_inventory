<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockBatch extends Model
{
    protected $fillable = ['batch_code', 'notes', 'total_cost'];

    protected function casts(): array
    {
        return [
            'total_cost' => 'float',
        ];
    }

    public function items()
    {
        return $this->hasMany(RestockBatchItem::class);
    }
}
