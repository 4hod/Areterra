<?php

namespace App\Http\Controllers;

use App\Events\IncidentLogged;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $canViewAll = Gate::allows('view_all_incidents');

        return Inertia::render('Incidents', [
            'incidents' => Incident::with('reportedBy:id,name')
                ->when(! $canViewAll, fn ($q) => $q->where('reported_by', $request->user()->id))
                ->orderByDesc('occurred_at')->get()
                ->map(fn ($i) => [
                    'id' => $i->id,
                    'title' => $i->title,
                    'occurred_at' => $i->occurred_at->toDateTimeString(),
                    'location' => $i->location,
                    'description' => $i->description,
                    'persons_involved' => $i->persons_involved,
                    'injury_details' => $i->injury_details,
                    'severity' => $i->severity,
                    'actions_taken' => $i->actions_taken,
                    'follow_up_required' => $i->follow_up_required,
                    'status' => $i->status,
                    'reported_by' => $i->reportedBy->name,
                ]),
            'canManage' => Gate::allows('manage_incidents'),
        ]);
    }

    public function store(Request $request)
    {
        $incident = Incident::create([
            ...$request->validate([
                'title' => ['required', 'string', 'max:200'],
                'occurred_at' => ['required', 'date'],
                'location' => ['nullable', 'string', 'max:200'],
                'description' => ['required', 'string'],
                'persons_involved' => ['nullable', 'string'],
                'injury_details' => ['nullable', 'string'],
                'severity' => ['required', 'in:'.implode(',', Incident::SEVERITIES)],
                'actions_taken' => ['nullable', 'string'],
                'follow_up_required' => ['boolean'],
            ]),
            'reported_by' => $request->user()->id,
        ]);

        IncidentLogged::dispatch($incident);

        return back()->with('success', 'Incident logged.');
    }

    public function update(Request $request, Incident $incident)
    {
        $incident->update($request->validate([
            'status' => ['sometimes', 'in:'.implode(',', Incident::STATUSES)],
            'actions_taken' => ['nullable', 'string'],
            'follow_up_required' => ['boolean'],
        ]));

        return back()->with('success', 'Incident updated.');
    }
}
