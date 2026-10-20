<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitorAttendance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function checkedOutBy()
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }
}
