<?php

namespace App\Models\Concerns;

use App\Models\Task;

/**
 * Point 11 — tasks work across everything. Add this trait to any model and it
 * can carry tasks without that model knowing anything about how tasks work.
 */
trait HasTasks
{
    public function tasks()
    {
        return $this->morphMany(Task::class, 'taskable')->latest('due_date');
    }

    public function openTasks()
    {
        return $this->tasks()->open();
    }

    public function addTask(array $attributes): Task
    {
        return $this->tasks()->create([
            'created_by' => auth()->id(),
            ...$attributes,
        ]);
    }
}
