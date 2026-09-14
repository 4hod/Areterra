<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\TransportRun;
use Carbon\CarbonInterface;

// The five Today-page items, each auto-checked from real data (SPEC.md §2).
class TodayChecklist
{
    public function build(CarbonInterface $date): array
    {
        $attendees = Attendance::whereDate('date', $date)->where('checked_in', true)->get();

        $transportExpected = Member::scheduledFor($date)
            ->whereHas('settings', fn ($q) => $q->where('transport_required', true))
            ->exists();

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
                'done' => $activeAnimals === 0 || $checkedAnimals >= $activeAnimals,
                'detail' => $checkedAnimals.' of '.$activeAnimals.' animals checked and fed',
            ],
            [
                'key' => 'transport',
                'label' => 'Transport Register',
                'done' => ! $transportExpected || TransportRun::whereDate('run_date', $date)->exists(),
                'detail' => $transportExpected
                    ? 'At least one transport run recorded today'
                    : 'No transport is scheduled today',
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
                'key' => 'end_of_day',
                'label' => 'End of Day Records',
                'done' => $attendees->isNotEmpty() && $eodCount >= $attendees->count(),
                'detail' => $eodCount.' of '.$attendees->count().' completed',
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
            $previousStepsDone = $previousStepsDone && $item['done'];

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
