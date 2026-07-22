<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MaintenanceTaskController extends Controller
{
    public function index()
    {
        $tasks = MaintenanceTask::with(['assignedTo:id,name', 'createdBy:id,name'])
            ->orderByRaw("completed_at IS NOT NULL")
            ->orderBy('due_date')
            ->get();

        return Inertia::render('Maintenance', [
            'tasks' => $tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->toDateString(),
                'assigned_to' => $t->assignedTo?->name,
                'created_by' => $t->createdBy->name,
                'status' => $t->effectiveStatus(),
                'notes' => $t->notes,
            ]),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('manage_operations'),
        ]);
    }

    public function store(Request $request)
    {
        MaintenanceTask::create([
            ...$request->validate([
                'title' => ['required', 'string', 'max:200'],
                'description' => ['nullable', 'string'],
                'priority' => ['required', 'in:low,medium,high'],
                'due_date' => ['nullable', 'date'],
                'assigned_to' => ['nullable', 'exists:users,id'],
            ]),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Task added.');
    }

    public function complete(MaintenanceTask $task)
    {
        $task->update(['completed_at' => now()]);

        return back()->with('success', "\"{$task->title}\" marked complete.");
    }

    public function update(Request $request, MaintenanceTask $task)
    {
        $task->update($request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Task updated.');
    }

    public function destroy(MaintenanceTask $task)
    {
        $task->delete();

        return back()->with('success', "\"{$task->title}\" removed.");
    }
}
