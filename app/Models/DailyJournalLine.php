<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyJournalLine extends Model
{
    protected $fillable = [
        'daily_journal_id',
        'item_id',
        'product_id',
        'restock_batch_item_id',
        'direction',
        'quantity',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'meta'     => 'array',
        ];
    }

    public function journal()
    {
        return $this->belongsTo(DailyJournal::class, 'daily_journal_id');
    }
}
