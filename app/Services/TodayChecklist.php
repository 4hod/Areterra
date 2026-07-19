<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\TransportRun;
use Carbon\CarbonInterface;

// The five Today-page items, each auto-checked from real data (SPEC.md §2).
class TodayChecklist
{
    public function build(CarbonInterface $date): array
    {
        $attendees = Attendance::whereDate('date', $date)->where('checked_in', true)->get();

        $activeAnimals = Animal::active()->count();
        $checkedAnimals = Animal::active()
            ->whereHas('welfareChecks', fn ($q) => $q->whereDate('created_at', $date))
            ->count();

        $eodCount = EndOfDayRecord::whereDate('date', $date)
            ->whereIn('member_id', $attendees->pluck('member_id'))
            ->count();

        return [
            [
                'key' => 'transport',
                'label' => 'Transport Register',
                'done' => TransportRun::whereDate('run_date', $date)->exists(),
                'detail' => 'At least one transport run recorded today',
            ],
            [
                'key' => 'register',
                'label' => 'Morning Register',
                'done' => $attendees->isNotEmpty(),
                'detail' => $attendees->count().' checked in',
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
