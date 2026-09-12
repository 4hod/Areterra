<?php

namespace App\Listeners;

use App\Events\MemberMarkedAbsent;
use App\Models\TransportRun;

/**
 * A genuine absence means no journeys at all that day — both legs are marked
 * absent so nobody drives out. "Not collected" deliberately does NOT do this:
 * the member may still be dropped home.
 */
class CancelRemainingLegsOnAbsence
{
    public function handle(MemberMarkedAbsent $event): void
    {
        $a = $event->attendance;

        TransportRun::whereDate('run_date', $a->date)
            ->where('member_id', $a->member_id)
            ->where('outcome', '!=', 'collected')
            ->update([
                'outcome' => 'absent',
                'outcome_reason' => $a->absence_reason ?: 'Member absent',
            ]);
    }
}
