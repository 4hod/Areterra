<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $visible = Task::query()
            ->when(! Gate::allows('manage_operations'), fn ($q) => $q->where(function ($owned) use ($request) {
                $owned->where('assigned_to', $request->user()->id)
                    ->orWhere('created_by', $request->user()->id);
            }));

        $tasks = (clone $visible)
            ->with(['taskable', 'assignee'])
            ->when($request->query('show') !== 'done', fn ($q) => $q->open())
            ->when($request->query('show') === 'done', fn ($q) => $q->whereNotNull('completed_at')->latest('completed_at'))
            ->when($request->query('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->orderByRaw('due_date is null, due_date')
            ->limit(200)
            ->get()
            ->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'priority' => $t->priority,
                'due_date' => $t->due_date?->toDateString(),
                'overdue' => $t->due_date && $t->due_date->isPast() && ! $t->isComplete(),
                'completed_at' => $t->completed_at?->toDateString(),
                'assignee' => $t->assignee?->name,
                // Which record this is about — the whole point of a shared task list.
                'about' => $t->taskable && (! ($t->taskable instanceof \App\Models\Member) || Gate::allows('view_member_details')) ? [
                    'type' => class_basename($t->taskable_type),
                    'name' => $t->taskable->name
                        ?? (method_exists($t->taskable, 'displayName') ? $t->taskable->displayName() : null)
                        ?? $t->taskable->title
                        ?? '#'.$t->taskable_id,
                ] : null,
                'automatic' => $t->completes_on_event !== null,
                'notes' => $t->notes,
            ]);

        return Inertia::render('Tasks', [
            'tasks' => $tasks,
            'show' => $request->query('show', 'open'),
            'mine' => (bool) $request->query('mine'),
            'staff' => User::orderBy('name')->get(['id', 'name']),
            'canManage' => Gate::allows('manage_operations'),
            'counts' => [
                'open' => (clone $visible)->open()->count(),
                'overdue' => (clone $visible)->overdue()->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        if (! Gate::allows('manage_operations')) {
            abort_if(isset($data['assigned_to']) && (int) $data['assigned_to'] !== $request->user()->id, 403);
            $data['assigned_to'] = $request->user()->id;
        }

        Task::create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Task added.');
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $task->update([
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Task completed.');
    }

    public function reopen(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $task->update(['completed_at' => null, 'completed_by' => null]);

        return back()->with('success', 'Task reopened.');
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeTask($request, $task);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'in:low,medium,high'],
        ]);
        if (! Gate::allows('manage_operations')) {
            abort_if(array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] !== $request->user()->id, 403);
        }
        $task->update($data);

        return back()->with('success', 'Task updated.');
    }

    private function authorizeTask(Request $request, Task $task): void
    {
        abort_unless(
            Gate::allows('manage_operations')
            || $task->assigned_to === $request->user()->id
            || $task->created_by === $request->user()->id,
            403,
        );
    }
}
