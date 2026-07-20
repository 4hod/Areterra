<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\Http\Request;
use Inertia\Inertia;

// The daily monitoring dashboard: date picker, progress bar, grouped by
// species with completion indicators (SPEC checklist: Daily Animal Monitoring).
class MonitoringPageController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? today();

        $animals = Animal::active()
            ->with(['dailyMonitoring' => fn ($q) => $q->whereDate('monitor_date', $date)])
            ->orderBy('species')->orderBy('name')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'species' => $a->species,
                'monitoring' => $a->dailyMonitoring->first(),
            ]);

        return Inertia::render('Monitoring', [
            'date' => $date->toDateString(),
            'bySpecies' => $animals->groupBy('species'),
            'done' => $animals->filter(fn ($a) => $a['monitoring'] !== null)->count(),
            'total' => $animals->count(),
        ]);
    }
}
