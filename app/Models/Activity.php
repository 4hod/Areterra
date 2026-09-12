<?php

namespace App\Models;

use App\Models\Concerns\HasTasks;
use App\Models\Concerns\HasDocuments;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes, HasTasks, HasDocuments;

    protected $guarded = [];

    public function participants()
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'activity_participants')
            ->withPivot(['attended', 'outcome_notes'])->withTimestamps();
    }

    public function animals()
    {
        return $this->belongsToMany(Animal::class, 'activity_participants')
            ->withPivot(['attended'])->withTimestamps();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
