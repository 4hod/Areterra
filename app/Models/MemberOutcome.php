<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberOutcome extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function goal()
    {
        return $this->belongsTo(MemberGoal::class, 'member_goal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
