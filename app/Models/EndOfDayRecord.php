<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EndOfDayRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'concern' => 'boolean',
            'medication_given' => 'boolean',
            'incident' => 'boolean',
            'photos' => 'array',
            'activities' => 'encrypted',
            'toileting_notes' => 'encrypted',
            'medication_notes' => 'encrypted',
            'incident_detail' => 'encrypted',
            'notes' => 'encrypted',
            'concern_detail' => 'encrypted',
        ];
    }

    public const INTAKE_LEVELS = ['good', 'some', 'poor', 'refused'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
