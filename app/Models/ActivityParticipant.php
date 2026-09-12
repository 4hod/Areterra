<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityParticipant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attended' => 'boolean'];
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
