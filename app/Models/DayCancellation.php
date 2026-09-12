<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DayCancellation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public static function isCancelled($date): bool
    {
        return static::whereDate('date', $date)->exists();
    }
}
