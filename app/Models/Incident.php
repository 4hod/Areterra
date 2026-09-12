<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasDocuments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasFactory, SoftDeletes, HasTasks, HasDocuments;

    protected $guarded = [];

    /** The member, animal or vehicle this incident is about. */
    public function subject()
    {
        return $this->morphTo();
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

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
