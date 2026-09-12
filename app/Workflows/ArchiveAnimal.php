<?php

namespace App\Workflows;

use App\Models\ActivityParticipant;
use App\Models\Animal;
use App\Models\Task;

/**
 * "Close all the open tasks — there shouldn't be any open task for them.
 *  Archive their welfare history, but keep it so we can still access it."
 *
 * Nothing is deleted. Welfare checks, vet records and incidents stay attached
 * to the animal and remain readable; the animal is marked archived so it stops
 * appearing in day-to-day lists.
 */
final class ArchiveAnimal extends Workflow
{
    public function __construct(
        private readonly Animal $animal,
        private readonly string $reason,
        private readonly ?int $userId = null,
    ) {}

    public function problems(): array
    {
        return $this->animal->archived_at
            ? ["{$this->animal->name} is already archived."]
            : [];
    }

    protected function summary(): string
    {
        return 'This animal could not be archived.';
    }

    protected function execute(): Animal
    {
        Task::query()->open()
            ->where('taskable_type', $this->animal->getMorphClass())
            ->where('taskable_id', $this->animal->getKey())
            ->update([
                'completed_at' => now(),
                'completed_by' => $this->userId,
                'notes' => 'Closed automatically — animal archived ('.$this->reason.').',
            ]);

        // Removed from sessions that haven't happened yet; past ones stand.
        ActivityParticipant::where('animal_id', $this->animal->id)
            ->whereHas('activity', fn ($q) => $q->whereDate('activity_date', '>', today()))
            ->update(['animal_id' => null]);

        $this->animal->update([
            'archived_at' => now(),
            'archived_reason' => $this->reason,
            'welfare_status' => 'archived',
        ]);

        return $this->animal->fresh();
    }
}
