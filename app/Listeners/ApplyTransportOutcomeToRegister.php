<?php

namespace App\Listeners;

use App\Events\MemberMarkedAbsent;
use App\Events\TransportOutcomeRecorded;
use App\Models\Attendance;

/**
 * Only `absent` touches the register.
 *
 * "Not collected" means the member didn't take that leg — an appointment, a
 * lift from family, going home early. They are still expected in, so marking
 * them absent would be wrong and would wrongly cancel their afternoon drop-off.
 */
class ApplyTransportOutcomeToRegister
{
    public function handle(TransportOutcomeRecorded $event): void
    {
        $run = $event->run;

        if ($run->outcome !== 'absent') {
            return;
        }

        $attendance = Attendance::updateOrCreate(
            ['member_id' => $run->member_id, 'date' => $run->run_date],
            [
                'status' => 'absent',
                'checked_in' => false,
                'checked_in_at' => null,
                'absence_reason' => $run->outcome_reason ?: 'Absent — reported at transport',
                'recorded_by' => $run->user_id,
            ],
        );

        MemberMarkedAbsent::dispatch($attendance);
    }
}
