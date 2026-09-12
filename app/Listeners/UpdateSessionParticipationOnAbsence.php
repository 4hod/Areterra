<?php

namespace App\Listeners;

use App\Events\MemberMarkedAbsent;
use App\Models\ActivityParticipant;

/**
 * An absent member did not take part in that day's sessions. Without this,
 * impact reporting counts them as having attended.
 */
class UpdateSessionParticipationOnAbsence
{
    public function handle(MemberMarkedAbsent $event): void
    {
        $attendance = $event->attendance;

        ActivityParticipant::query()
            ->where('member_id', $attendance->member_id)
            ->whereHas('activity', fn ($q) => $q->whereDate('activity_date', $attendance->date))
            ->update([
                'attended' => false,
                'outcome_notes' => 'Member absent'.($attendance->absence_reason ? ' — '.$attendance->absence_reason : ''),
            ]);
    }
}
