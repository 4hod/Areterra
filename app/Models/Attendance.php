<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    public const MOODS = ['happy', 'neutral', 'sad', 'angry', 'anxious'];

    protected $guarded = [];

    public const STATUSES = ['expected', 'present', 'absent'];

    public function scopeAbsent($query)
    {
        return $query->where('status', 'absent');
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

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
