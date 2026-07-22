<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public const SEVERITIES = ['minor', 'moderate', 'serious', 'critical'];
    public const STATUSES = ['open', 'under-review', 'closed'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'follow_up_required' => 'boolean',
            'description' => 'encrypted',
            'persons_involved' => 'encrypted',
            'injury_details' => 'encrypted',
        ];
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
