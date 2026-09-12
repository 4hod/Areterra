<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SafeguardingConcern extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /** The end-of-day record this concern was raised from, if any. */
    public function endOfDayRecord()
    {
        return $this->belongsTo(EndOfDayRecord::class);
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'closed_at' => 'datetime',
            'details' => 'encrypted',
            'actions_taken' => 'encrypted',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
