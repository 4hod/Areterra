<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpactEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'outcome_note' => 'encrypted',
            'evidence_tags' => 'array',
            'engagement_rating' => 'integer',
            'independence_rating' => 'integer',
        ];
    }

    public function member() { return $this->belongsTo(Member::class); }
    public function goal() { return $this->belongsTo(MemberGoal::class, 'member_goal_id'); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function animal() { return $this->belongsTo(Animal::class); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
}
