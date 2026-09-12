<?php

namespace App\Listeners;

use App\Events\MemberMarkedAbsent;
use App\Models\Attendance;
use App\Models\DayCancellation;

/**
 * "Twenty four — two."
 *
 * Two consecutive unexplained absences raises a welfare task on the member.
 * Absences with a reason recorded don't count, and cancelled days are skipped
 * entirely — the Hub closing is not the member failing to attend.
 */
class RaiseWelfareTaskOnRepeatAbsence
{
    private const THRESHOLD = 2;

    public function handle(MemberMarkedAbsent $event): void
    {
        $attendance = $event->attendance;

        if (filled($attendance->absence_reason)) {
            return;
        }

        $recent = Attendance::where('member_id', $attendance->member_id)
            ->whereDate('date', '<=', $attendance->date)
            ->orderByDesc('date')
            ->limit(self::THRESHOLD * 3)
            ->get()
            ->reject(fn ($a) => DayCancellation::isCancelled($a->date))
            ->take(self::THRESHOLD);

        $unexplained = $recent->count() === self::THRESHOLD
            && $recent->every(fn ($a) => $a->status === 'absent' && blank($a->absence_reason));

        if (! $unexplained) {
            return;
        }

        $member = $attendance->member;

        if (! $member || $member->openTasks()->where('completes_on_event', 'member_contacted')->exists()) {
            return;
        }

        $member->addTask([
            'title' => "Contact {$member->displayName()} — ".self::THRESHOLD.' unexplained absences',
            'description' => 'Raised automatically. No reason was recorded for either day.',
            'priority' => 'high',
            'due_date' => today(),
            'completes_on_event' => 'member_contacted',
            'created_by' => $attendance->recorded_by,
        ]);
    }
}
