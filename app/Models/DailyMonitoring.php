<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyMonitoring extends Model
{
    use HasFactory;

    protected $table = 'daily_monitoring';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'monitor_date' => 'date',
            'concern' => 'boolean',
        ];
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
