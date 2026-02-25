<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DailyJournal;

class RestockBatch extends Model
{
    protected $fillable = ['batch_code', 'notes', 'total_cost', 'journal_id'];

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

    public function journal()
    {
        return $this->belongsTo(DailyJournal::class, 'journal_id');
    }
}
