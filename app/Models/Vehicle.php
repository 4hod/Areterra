<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mot_due' => 'date',
            'service_due' => 'date',
            'active' => 'boolean',
        ];
    }

    public function defects()
    {
        return $this->hasMany(VehicleDefect::class);
    }

    public function openDefects()
    {
        return $this->defects()->whereNull('resolved_at');
    }
}
