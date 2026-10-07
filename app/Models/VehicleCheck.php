<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleCheck extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'odometer_miles' => 'decimal:1',
            'tyres_ok' => 'boolean',
            'lights_ok' => 'boolean',
            'warning_lights_ok' => 'boolean',
            'damage_ok' => 'boolean',
            'safe_to_drive' => 'boolean',
            'notes' => 'encrypted',
        ];
    }

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function checker() { return $this->belongsTo(User::class, 'checked_by'); }
}
