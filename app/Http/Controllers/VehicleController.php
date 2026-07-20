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
            'vehicles' => Vehicle::with(['defects' => fn ($q) => $q->with('reporter:id,name')->orderByDesc('date')])
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

    public function resolveDefect(VehicleDefect $defect)
    {
        Gate::authorize('manage_vehicles');

        $defect->update(['resolved_at' => now()]);

        return back()->with('success', 'Defect resolved.');
    }
}
