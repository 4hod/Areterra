<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    public const MOODS = ['happy', 'neutral', 'sad', 'angry', 'anxious'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'checked_in' => 'boolean',
            'checked_in_at' => 'datetime',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
