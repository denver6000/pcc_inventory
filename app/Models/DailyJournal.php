<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyJournal extends Model
{
    protected $fillable = ['journal_date', 'kind', 'notes', 'closed_at'];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'closed_at'    => 'datetime',
        ];
    }

    public function lines()
    {
        return $this->hasMany(DailyJournalLine::class);
    }
}
