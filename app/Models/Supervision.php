<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supervision extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['supervision', 'appraisal', 'probation_review', 'return_to_work', 'informal', 'disciplinary'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'next_due_date' => 'date',
            'staff_signed_off' => 'boolean',
        ];
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
