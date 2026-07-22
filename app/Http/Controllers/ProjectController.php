<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index()
    {
        return Inertia::render('Projects', [
            'projects' => Project::with('createdBy:id,name')->orderByDesc('start_date')->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'description' => $p->description,
                    'status' => $p->status,
                    'start_date' => $p->start_date?->toDateString(),
                    'end_date' => $p->end_date?->toDateString(),
                    'created_by' => $p->createdBy->name,
                ]),
            'canManage' => Gate::allows('manage_operations'),
        ]);
    }

    public function store(Request $request)
    {
        Project::create([
            ...$request->validate([
                'title' => ['required', 'string', 'max:200'],
                'description' => ['nullable', 'string'],
                'status' => ['required', 'in:'.implode(',', Project::STATUSES)],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
            ]),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Project created.');
    }

    public function update(Request $request, Project $project)
    {
        $project->update($request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:'.implode(',', Project::STATUSES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]));

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return back()->with('success', "\"{$project->title}\" removed.");
    }
}
