<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\TransportRun;
use Carbon\CarbonInterface;

// The Today-page items, each auto-checked from real data (SPEC.md §2).
class TodayChecklist
{
    public function build(CarbonInterface $date): array
    {
        $attendanceRecords = Attendance::whereDate('date', $date)->get();
        $attendees = $attendanceRecords->where('checked_in', true);
        $scheduledMemberIds = Member::scheduledFor($date)->pluck('members.id');
        $memberWorkExpected = $scheduledMemberIds->isNotEmpty() || $attendees->isNotEmpty();

        $transportMemberIds = Member::scheduledFor($date)
            ->whereHas('settings', fn ($q) => $q->where('transport_required', true))
            ->pluck('members.id');
        $transportExpected = $transportMemberIds->isNotEmpty();
        $transportRuns = TransportRun::whereDate('run_date', $date)
            ->whereIn('member_id', $transportMemberIds)
            ->get();
        $morningRuns = $transportRuns->where('phase', 'morning')->keyBy('member_id');
        $returnRuns = $transportRuns->where('phase', 'afternoon')->keyBy('member_id');
        $morningComplete = ! $transportExpected || $transportMemberIds->every(
            fn ($memberId) => $morningRuns->has($memberId),
        );
        $returnComplete = ! $transportExpected || ($morningComplete && $transportMemberIds->every(
            fn ($memberId) => $morningRuns->get($memberId)?->outcome === 'absent' || $returnRuns->has($memberId),
        ));

        $activeAnimals = Animal::active()->count();
        $checkedAnimals = Animal::active()
            ->whereHas('welfareChecks', fn ($q) => $q
                ->whereDate('created_at', $date)
                ->where('fed', true))
            ->count();

        $eodCount = EndOfDayRecord::whereDate('date', $date)
            ->whereIn('member_id', $attendees->pluck('member_id'))
            ->count();

        $items = [
            [
                'key' => 'welfare',
                'label' => 'Animal Welfare & Feeding',
                'applicable' => true,
                'done' => $activeAnimals === 0 || $checkedAnimals >= $activeAnimals,
                'detail' => $checkedAnimals.' of '.$activeAnimals.' animals checked and fed',
            ],
            [
                'key' => 'transport',
                'label' => 'Morning Transport',
                'applicable' => $transportExpected,
                'done' => $morningComplete,
                'detail' => $transportExpected
                    ? $morningRuns->count().' of '.$transportMemberIds->count().' collections recorded'
                    : 'No transport is scheduled today',
            ],
            [
                'key' => 'register',
                'label' => 'Morning Register',
                'applicable' => $memberWorkExpected,
                'done' => $memberWorkExpected && $scheduledMemberIds->every(
                    fn ($memberId) => $attendanceRecords->contains(fn ($attendance) =>
                        (int) $attendance->member_id === (int) $memberId
                        && ($attendance->checked_in || $attendance->status === 'absent')
                    ),
                ),
                'detail' => $memberWorkExpected ? $attendees->count().' checked in; '.$attendanceRecords->where('status', 'absent')->count().' absent' : 'No members are scheduled today',
            ],
            [
                'key' => 'moods',
                'label' => 'Arrival Moods',
                'applicable' => $memberWorkExpected,
                'done' => $attendees->isNotEmpty() && $attendees->every(fn ($a) => $a->arrival_mood !== null),
                'detail' => $memberWorkExpected ? $attendees->whereNotNull('arrival_mood')->count().' of '.$attendees->count().' recorded' : 'No member arrivals are expected',
            ],
            [
                'key' => 'end_of_day',
                'label' => 'End of Day Records',
                'applicable' => $memberWorkExpected,
                'done' => $attendees->isNotEmpty() && $eodCount >= $attendees->count(),
                'detail' => $memberWorkExpected ? $eodCount.' of '.$attendees->count().' completed' : 'No member records are due today',
            ],
            [
                'key' => 'return_transport',
                'label' => 'Return Transport',
                'applicable' => $transportExpected,
                'done' => $returnComplete,
                'detail' => $transportExpected
                    ? $returnRuns->count().' of '.$transportMemberIds->count().' returns recorded'
                    : 'No return transport is scheduled today',
            ],
        ];

        // Welfare and feeding run alongside the day and are always available.
        // The remaining operational jobs form the strictly ordered sequence.
        $previousStepsDone = true;

        return collect($items)->map(function (array $item) use (&$previousStepsDone) {
            if ($item['key'] === 'welfare') {
                $item['available'] = true;

                return $item;
            }

            $item['available'] = $previousStepsDone;
            $previousStepsDone = $previousStepsDone && (! $item['applicable'] || $item['done']);

            return $item;
        })->all();
    }

    public function canAccess(string $step, CarbonInterface $date): bool
    {
        // Welfare and feeding are safety-critical and must always remain
        // recordable, even if another daily job is currently in progress.
        if ($step === 'welfare') {
            return true;
        }

        $item = collect($this->build($date))->firstWhere('key', $step);

        return (bool) ($item['available'] ?? false);
    }
}
