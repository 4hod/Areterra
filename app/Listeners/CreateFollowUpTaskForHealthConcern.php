<?php

namespace App\Listeners;

use App\Events\AnimalHealthConcernRaised;
use App\Models\Task;

/**
 * Point 3, made concrete: raising a health concern on a welfare check creates
 * the vet follow-up task by itself. Nobody types it a second time.
 *
 * Laravel 11+ auto-discovers listeners in this directory by their type-hint —
 * no registration needed.
 */
class CreateFollowUpTaskForHealthConcern
{
    public function handle(AnimalHealthConcernRaised $event): void
    {
        $check = $event->concern;
        $animal = $check->animal ?? null;

        if (! $animal) {
            return;
        }

        // Don't stack duplicates if a concern is raised twice in a day.
        $exists = Task::query()->open()
            ->where('taskable_type', $animal->getMorphClass())
            ->where('taskable_id', $animal->getKey())
            ->where('completes_on_event', 'vet_record_created')
            ->exists();

        if ($exists) {
            return;
        }

        $animal->addTask([
            'title' => "Vet follow-up for {$animal->name}",
            'description' => 'Raised automatically from a welfare check health concern.',
            'priority' => 'high',
            'due_date' => today()->addDay(),
            'completes_on_event' => 'vet_record_created',
            'created_by' => $check->recorded_by ?? null,
        ]);
    }
}
