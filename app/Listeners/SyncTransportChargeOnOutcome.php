<?php

namespace App\Listeners;

use App\Events\MemberMarkedAbsent;
use App\Events\MemberMarkedPresent;
use App\Events\TransportOutcomeRecorded;
use App\Events\TransportRunUndone;
use App\Support\TransportCharges;
use Illuminate\Events\Dispatcher;

/**
 * Any change to a day's transport or attendance recomputes that day's charge.
 *
 * One listener for four events, because the answer is the same in every case:
 * work out what the day should cost from the legs actually travelled. That is
 * why "not collected in the morning, dropped back in the afternoon" lands on
 * £2.50 without anyone doing arithmetic.
 */
class SyncTransportChargeOnOutcome
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            TransportOutcomeRecorded::class => 'fromRun',
            TransportRunUndone::class => 'fromRun',
            MemberMarkedAbsent::class => 'fromAttendance',
            MemberMarkedPresent::class => 'fromAttendance',
        ];
    }

    public function fromRun(object $event): void
    {
        TransportCharges::syncForDay($event->run->member_id, $event->run->run_date, $event->run->user_id);
    }

    public function fromAttendance(object $event): void
    {
        $a = $event->attendance;
        TransportCharges::syncForDay($a->member_id, $a->date, $a->recorded_by);
    }
}
