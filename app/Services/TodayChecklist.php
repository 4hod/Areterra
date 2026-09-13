<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\DayCancellation;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\TransportRun;
use Carbon\CarbonInterface;

// The five Today-page items, each auto-checked from real data (SPEC.md §2).
class TodayChecklist
{
    public function build(CarbonInterface $date): array
    {
        $scheduled = Member::scheduledFor($date)->with('settings')->get();
        // Include ad-hoc attendees as well as scheduled members. The register is
        // complete only when every visible person has a final present/absent
        // decision, rather than as soon as the first person is checked in.
        $attendance = Attendance::whereDate('date', $date)->get()->keyBy('member_id');
        $attendees = $attendance->where('checked_in', true);
        $dayCancelled = DayCancellation::isCancelled($date);
        $registerComplete = $dayCancelled || (
            $attendance->isNotEmpty()
            && $attendance->every(fn ($entry) => $entry->checked_in || $entry->status === 'absent')
            && $scheduled->every(fn ($member) => $attendance->has($member->id))
        );

        $activeAnimals = Animal::active()->count();
        $checkedAnimals = Animal::active()
            ->whereHas('welfareChecks', fn ($q) => $q->whereDate('created_at', $date))
            ->count();

        $eodCount = EndOfDayRecord::whereDate('date', $date)
            ->whereIn('member_id', $attendees->pluck('member_id'))
            ->count();

        $transportMembers = $scheduled->filter(fn ($member) => (bool) $member->settings?->transport_required);
        $transportRuns = TransportRun::whereDate('run_date', $date)
            ->whereIn('member_id', $transportMembers->pluck('id'))
            ->get()
            ->groupBy('member_id');
        $transportComplete = $dayCancelled || $transportMembers->isEmpty() || $transportMembers->every(function ($member) use ($transportRuns) {
            $runs = $transportRuns->get($member->id, collect());
            $morning = $runs->firstWhere('phase', 'morning');

            return $morning && ($morning->outcome === 'absent' || $runs->contains('phase', 'afternoon'));
        });

        return [
            [
                'key' => 'transport',
                'label' => 'Transport Register',
                'done' => $transportComplete,
                'detail' => $transportMembers->isEmpty()
                    ? 'No transport runs scheduled today'
                    : $transportRuns->flatten(1)->count().' journey outcomes recorded',
            ],
            [
                'key' => 'register',
                'label' => 'Morning Register',
                'done' => $registerComplete,
                'detail' => $dayCancelled
                    ? 'The whole day is cancelled'
                    : $attendance->count().' of '.$scheduled->count().' attendance decisions recorded',
            ],
            [
                'key' => 'moods',
                'label' => 'Arrival Moods',
                'done' => $attendees->isNotEmpty() && $attendees->every(fn ($a) => $a->arrival_mood !== null),
                'detail' => $attendees->whereNotNull('arrival_mood')->count().' of '.$attendees->count().' recorded',
            ],
            [
                'key' => 'welfare',
                'label' => 'Animal Welfare Checks',
                'done' => $activeAnimals > 0 && $checkedAnimals >= $activeAnimals,
                'detail' => $checkedAnimals.' of '.$activeAnimals.' animals checked',
            ],
            [
                'key' => 'end_of_day',
                'label' => 'End of Day Records',
                'done' => $attendees->isNotEmpty() && $eodCount >= $attendees->count(),
                'detail' => $eodCount.' of '.$attendees->count().' completed',
            ],
        ];
    }
}
