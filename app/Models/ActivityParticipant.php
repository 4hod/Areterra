<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ActivityParticipant extends Pivot
{
    public $incrementing = true;

    protected $table = 'activity_participants';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'outcome_notes' => 'encrypted',
        ];
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }
}
