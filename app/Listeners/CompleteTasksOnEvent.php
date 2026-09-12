<?php

namespace App\Listeners;

use App\Models\Task;
use Illuminate\Events\Dispatcher;

/**
 * Point 11 — "Review Rico's medication" disappears once the review is recorded.
 *
 * Tasks carry a `completes_on_event` key. Any domain event whose class basename
 * maps to that key closes the matching open tasks on the same record.
 */
class CompleteTasksOnEvent
{
    /** Domain event => the completes_on_event key it satisfies. */
    private const MAP = [
        'AnimalHealthConcernRaised' => null,          // raises, never completes
        'VetRecordCreated' => 'vet_record_created',
        'MemberAttended' => 'member_attended',
        'InvoicePaid' => 'invoice_paid',
        'GrantExpenseRecorded' => 'grant_expense_recorded',
        'PayrollApproved' => 'payroll_approved',
    ];

    public function subscribe(Dispatcher $events): array
    {
        $map = [];

        foreach (self::MAP as $event => $key) {
            if ($key !== null) {
                $map['App\\Events\\'.$event] = 'handle';
            }
        }

        return $map;
    }

    public function handle(object $event): void
    {
        $key = self::MAP[class_basename($event)] ?? null;

        if ($key === null) {
            return;
        }

        // The event's single public property is the subject record.
        $subject = collect(get_object_vars($event))->first();

        if (! is_object($subject) || ! method_exists($subject, 'getMorphClass')) {
            return;
        }

        Task::query()->open()
            ->where('completes_on_event', $key)
            ->where('taskable_type', $subject->getMorphClass())
            ->where('taskable_id', $subject->getKey())
            ->update([
                'completed_at' => now(),
                'completed_by' => auth()->id(),
                'notes' => 'Completed automatically when the underlying action was recorded.',
            ]);
    }
}
