<?php

namespace App\Workflows;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\DayCancellation;
use App\Models\Member;
use App\Models\TransportRun;
use App\Support\TransportCharges;
use Carbon\CarbonImmutable;

/**
 * "If there's no staff that can come in, all of it is cancelled — the transport
 * and everything."
 *
 * One action closes the whole day: sessions cancelled, both transport legs
 * stood down, nobody charged, and every scheduled member's file shows the day
 * was off rather than showing them absent. That distinction matters — a
 * cancelled day is not the member's absence and must never read like one.
 */
final class CancelDay extends Workflow
{
    public function __construct(
        private readonly CarbonImmutable $date,
        private readonly string $reason,
        private readonly ?int $userId = null,
    ) {}

    public function problems(): array
    {
        if (DayCancellation::isCancelled($this->date)) {
            return ['That day is already marked as cancelled.'];
        }

        if ($this->date->isBefore(today()->subMonth())) {
            return ['That date is more than a month ago — cancel it only if you are correcting the record.'];
        }

        return [];
    }

    protected function summary(): string
    {
        return 'The day could not be cancelled.';
    }

    protected function execute(): DayCancellation
    {
        $cancellation = DayCancellation::create([
            'date' => $this->date->toDateString(),
            'reason' => $this->reason,
            'cancelled_by' => $this->userId,
        ]);

        Activity::whereDate('activity_date', $this->date)->update([
            'status' => 'cancelled',
            'cancellation_reason' => $this->reason,
        ]);

        TransportRun::whereDate('run_date', $this->date)->update([
            'outcome' => 'not_collected',
            'outcome_reason' => 'Day cancelled — '.$this->reason,
            'completed_at' => null,
        ]);

        foreach (Member::scheduledFor($this->date)->get() as $member) {
            // Recorded as "not on" rather than absent — this was not their doing.
            Attendance::updateOrCreate(
                ['member_id' => $member->id, 'date' => $this->date->toDateString()],
                [
                    'status' => 'expected',
                    'checked_in' => false,
                    'checked_in_at' => null,
                    'absence_reason' => 'Day cancelled — '.$this->reason,
                    'recorded_by' => $this->userId,
                ],
            );

            // No journeys happened, so nothing is owed.
            TransportCharges::syncForDay($member->id, $this->date, $this->userId);
        }

        return $cancellation;
    }
}
