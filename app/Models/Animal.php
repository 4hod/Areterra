<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Animal extends Model
{
    use HasFactory, SoftDeletes;

    public const SPECIES = ['Macaw', 'Chinchilla', 'Degu', 'Guinea Pig', 'Rabbit', 'Chicken'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    public function welfareChecks()
    {
        return $this->hasMany(WelfareCheck::class);
    }

    public function dailyMonitoring()
    {
        return $this->hasMany(DailyMonitoring::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function checkedToday(): bool
    {
        return $this->welfareChecks()->whereDate('created_at', today())->exists();
    }
}
