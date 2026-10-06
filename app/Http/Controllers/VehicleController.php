<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleDefect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class VehicleController extends Controller
{
    public function index()
    {
        return Inertia::render('Vehicles', [
            'vehicles' => Vehicle::with([
                'defects' => fn ($q) => $q->with('reporter:id,name')->orderByDesc('date'),
                'checks' => fn ($q) => $q->with('checker:id,name')->limit(20),
            ])
                ->orderBy('registration')
                ->get()
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'registration' => $v->registration,
                    'make_model' => $v->make_model,
                    'mot_due' => $v->mot_due?->toDateString(),
                    'service_due' => $v->service_due?->toDateString(),
                    'mot_soon' => $v->mot_due !== null && $v->mot_due->lte(today()->addDays(30)),
                    'active' => $v->active,
                    'open_defects' => $v->defects->whereNull('resolved_at')->count(),
                    'defects' => $v->defects->map(fn ($d) => [
                        'id' => $d->id,
                        'date' => $d->date->toDateString(),
                        'description' => $d->description,
                        'severity' => $d->severity,
                        'reporter' => $d->reporter->name,
                        'resolved_at' => $d->resolved_at?->toDateString(),
                    ])->values(),
                    'last_mileage' => $v->checks->first()?->odometer_miles !== null
                        ? (float) $v->checks->first()->odometer_miles : null,
                    'last_check' => $v->checks->first() ? [
                        'checked_at' => $v->checks->first()->checked_at->toIso8601String(),
                        'checker' => $v->checks->first()->checker->name,
                        'safe_to_drive' => $v->checks->first()->safe_to_drive,
                    ] : null,
                    'checks' => $v->checks->map(fn ($check) => [
                        'id' => $check->id,
                        'checked_at' => $check->checked_at->toIso8601String(),
                        'checker' => $check->checker->name,
                        'odometer_miles' => (float) $check->odometer_miles,
                        'fuel_level' => $check->fuel_level,
                        'tyres_ok' => $check->tyres_ok,
                        'lights_ok' => $check->lights_ok,
                        'warning_lights_ok' => $check->warning_lights_ok,
                        'damage_ok' => $check->damage_ok,
                        'safe_to_drive' => $check->safe_to_drive,
                        'notes' => $check->notes,
                    ])->values(),
                ]),
            'canManage' => Gate::allows('manage_vehicles'),
        ]);
    }

    public function store(Request $request)
    {
        Vehicle::create($request->validate([
            'registration' => ['required', 'string', 'max:10'],
            'make_model' => ['nullable', 'string', 'max:100'],
            'mot_due' => ['nullable', 'date'],
            'service_due' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Vehicle added.');
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validate([
            'mot_due' => ['nullable', 'date'],
            'service_due' => ['nullable', 'date'],
            'active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Vehicle updated.');
    }

    public function storeDefect(Request $request, Vehicle $vehicle)
    {
        $vehicle->defects()->create([
            ...$request->validate([
                'description' => ['required', 'string'],
                'severity' => ['required', 'in:minor,serious,vehicle_off_road'],
            ]),
            'date' => today(),
            'reported_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Defect reported.');
    }

    public function storeCheck(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'odometer_miles' => ['required', 'numeric', 'min:0'],
            'fuel_level' => ['required', 'in:empty,quarter,half,three_quarters,full'],
            'tyres_ok' => ['required', 'boolean'],
            'lights_ok' => ['required', 'boolean'],
            'warning_lights_ok' => ['required', 'boolean'],
            'damage_ok' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lastMileage = $vehicle->checks()->value('odometer_miles');
        if ($lastMileage !== null && (float) $data['odometer_miles'] < (float) $lastMileage) {
            return back()->withErrors(['odometer_miles' => 'Mileage cannot be lower than the previous vehicle check.']);
        }

        $safe = collect(['tyres_ok', 'lights_ok', 'warning_lights_ok', 'damage_ok'])
            ->every(fn ($field) => (bool) $data[$field]);

        $check = $vehicle->checks()->create([
            ...$data,
            'safe_to_drive' => $safe,
            'checked_at' => now(),
            'checked_by' => $request->user()->id,
        ]);

        if (! $safe) {
            $failed = collect([
                'tyres_ok' => 'tyres', 'lights_ok' => 'lights',
                'warning_lights_ok' => 'dashboard warning lights', 'damage_ok' => 'damage/bodywork',
            ])->filter(fn ($label, $field) => ! $data[$field])->values()->join(', ');
            $vehicle->defects()->create([
                'reported_by' => $request->user()->id,
                'date' => today(),
                'description' => 'Pre-drive check failed: '.$failed.($data['notes'] ? ' — '.$data['notes'] : ''),
                'severity' => 'vehicle_off_road',
            ]);
        }

        return back()->with('success', $check->safe_to_drive
            ? 'Pre-drive check saved. Vehicle is recorded as safe to drive.'
            : 'Pre-drive check saved. Vehicle marked off road and a defect was opened.');
    }

    public function resolveDefect(VehicleDefect $defect)
    {
        Gate::authorize('manage_vehicles');

        $defect->update(['resolved_at' => now()]);

        return back()->with('success', 'Defect resolved.');
    }
}
