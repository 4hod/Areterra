<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCheck;
use Illuminate\Validation\ValidationException;

class VehicleCheckRecorder
{
    public function record(Vehicle $vehicle, User $user, array $data): VehicleCheck
    {
        $lastMileage = $vehicle->checks()->value('odometer_miles');
        if ($lastMileage !== null && (float) $data['odometer_miles'] < (float) $lastMileage) {
            throw ValidationException::withMessages([
                'odometer_miles' => 'Mileage cannot be lower than the previous vehicle check.',
            ]);
        }

        $safe = collect(['tyres_ok', 'lights_ok', 'warning_lights_ok', 'damage_ok'])
            ->every(fn ($field) => (bool) $data[$field]);

        $check = $vehicle->checks()->create([
            ...$data,
            'safe_to_drive' => $safe,
            'checked_at' => now(),
            'checked_by' => $user->id,
        ]);

        if (! $safe) {
            $failed = collect([
                'tyres_ok' => 'tyres', 'lights_ok' => 'lights',
                'warning_lights_ok' => 'dashboard warning lights', 'damage_ok' => 'damage/bodywork',
            ])->filter(fn ($label, $field) => ! $data[$field])->values()->join(', ');

            $vehicle->defects()->create([
                'reported_by' => $user->id,
                'date' => today(),
                'description' => 'Pre-drive check failed: '.$failed.(! empty($data['notes']) ? ' — '.$data['notes'] : ''),
                'severity' => 'vehicle_off_road',
            ]);
        }

        return $check;
    }
}
