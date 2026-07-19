<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeclockEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workedMinutes(): ?int
    {
        if (! $this->clock_out) {
            return null;
        }

        return max(0, (int) $this->clock_in->diffInMinutes($this->clock_out) - $this->break_minutes);
    }
}
