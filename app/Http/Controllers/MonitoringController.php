<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'monitor_date' => ['required', 'date'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'body_condition' => ['nullable', 'integer', 'between:1,5'],
            'coat_condition' => ['nullable', 'string', 'max:100'],
            'appetite' => ['nullable', 'string', 'max:100'],
            'droppings' => ['nullable', 'string', 'max:100'],
            'behaviour' => ['nullable', 'string', 'max:100'],
            'enrichment' => ['nullable', 'string', 'max:255'],
            'enrichment_minutes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'concern' => ['boolean'],
        ]);

        // Same animal, same day → update, never duplicate (SPEC.md technical note 6).
        // Carbon (not a Y-m-d string) so the lookup matches the cast storage format.
        $date = \Illuminate\Support\Carbon::parse($data['monitor_date'])->startOfDay();
        $animal->dailyMonitoring()->updateOrCreate(
            ['monitor_date' => $date],
            [...$data, 'monitor_date' => $date, 'user_id' => $request->user()->id],
        );

        return back()->with('success', "Monitoring saved for {$animal->name}.");
    }
}
