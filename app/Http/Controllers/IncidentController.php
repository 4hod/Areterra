<?php

namespace App\Http\Controllers;

use App\Events\IncidentLogged;

use App\Models\Incident;
use App\Support\RecordLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $canViewAll = Gate::allows('view_all_incidents');
        $context = RecordLinks::resolve($request->query('about'), $request->query('id'));

        return Inertia::render('Incidents', [
            'incidents' => Incident::with(['reportedBy:id,name', 'subject'])
                ->when($context, fn ($query) => $context instanceof Incident
                    ? $query->whereKey($context->getKey())
                    : $query->whereMorphedTo('subject', $context))
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
                    'about' => $i->subject ? RecordLinks::metadataIfVisible($i->subject) : null,
                ]),
            'canManage' => Gate::allows('manage_incidents'),
            'relatedOptions' => RecordLinks::options(['member', 'animal', 'vehicle']),
            'context' => $context ? RecordLinks::metadata($context) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
                'title' => ['required', 'string', 'max:200'],
                'occurred_at' => ['required', 'date'],
                'location' => ['nullable', 'string', 'max:200'],
                'description' => ['required', 'string'],
                'persons_involved' => ['nullable', 'string'],
                'injury_details' => ['nullable', 'string'],
                'severity' => ['required', 'in:'.implode(',', Incident::SEVERITIES)],
                'actions_taken' => ['nullable', 'string'],
                'follow_up_required' => ['boolean'],
                'related_type' => ['nullable', 'required_with:related_id', 'in:member,animal,vehicle'],
                'related_id' => ['nullable', 'required_with:related_type', 'integer'],
            ]);
        $related = RecordLinks::resolve($data['related_type'] ?? null, $data['related_id'] ?? null);
        unset($data['related_type'], $data['related_id']);

        $incident = new Incident([
            ...$data,
            'reported_by' => $request->user()->id,
        ]);
        if ($related) {
            $incident->subject()->associate($related);
        }
        $incident->save();

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
