<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LeaveCalendar
{
    public const DEFAULT_WORKING_DAYS = [1, 2, 4, 5];

    /**
     * Return chargeable leave split by entitlement year.
     *
     * @return array<int, float>
     */
    public static function daysByYear(
        User $user,
        CarbonInterface $start,
        CarbonInterface $end,
        bool $startHalfDay = false,
        bool $endHalfDay = false,
    ): array {
        $workingDays = array_map('intval', $user->working_days ?: self::DEFAULT_WORKING_DAYS);
        $result = [];

        for ($day = CarbonImmutable::instance($start)->startOfDay(); $day->lte($end); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $workingDays, true) || self::isBankHoliday($day)) {
                continue;
            }

            $value = 1.0;
            $isStart = $day->isSameDay($start);
            $isEnd = $day->isSameDay($end);

            // A one-day request marked as either half day is half a day, not zero.
            if (($isStart && $startHalfDay) || ($isEnd && $endHalfDay)) {
                $value = 0.5;
            }

            $result[$day->year] = ($result[$day->year] ?? 0.0) + $value;
        }

        ksort($result);

        return $result;
    }

    /** @return array<int, string> */
    public static function bankHolidays(int $year): array
    {
        $holidays = [];

        self::addWithWeekendSubstitute($holidays, CarbonImmutable::create($year, 1, 1));

        $easterSunday = CarbonImmutable::create($year, 3, 21)->addDays(easter_days($year));
        $holidays[] = $easterSunday->subDays(2)->toDateString();
        $holidays[] = $easterSunday->addDay()->toDateString();

        $holidays[] = CarbonImmutable::create($year, 5, 1)->nextOrSame(CarbonInterface::MONDAY)->toDateString();
        $holidays[] = CarbonImmutable::create($year, 5, 31)->previousOrSame(CarbonInterface::MONDAY)->toDateString();
        $holidays[] = CarbonImmutable::create($year, 8, 31)->previousOrSame(CarbonInterface::MONDAY)->toDateString();

        // Christmas and Boxing Day share the next two free weekdays when either
        // falls at a weekend.
        $christmas = CarbonImmutable::create($year, 12, 25);
        $boxingDay = CarbonImmutable::create($year, 12, 26);
        foreach ([$christmas, $boxingDay] as $holiday) {
            if (! $holiday->isWeekend()) {
                $holidays[] = $holiday->toDateString();
            }
        }

        $substitute = CarbonImmutable::create($year, 12, 27);
        foreach ([$christmas, $boxingDay] as $holiday) {
            if (! $holiday->isWeekend()) {
                continue;
            }

            while ($substitute->isWeekend() || in_array($substitute->toDateString(), $holidays, true)) {
                $substitute = $substitute->addDay();
            }
            $holidays[] = $substitute->toDateString();
            $substitute = $substitute->addDay();
        }

        foreach (config("leave.extra_bank_holidays.{$year}", []) as $date) {
            $holidays[] = $date;
        }

        return array_values(array_unique($holidays));
    }

    private static function isBankHoliday(CarbonInterface $date): bool
    {
        return in_array($date->toDateString(), self::bankHolidays($date->year), true);
    }

    /** @param array<int, string> $holidays */
    private static function addWithWeekendSubstitute(array &$holidays, CarbonImmutable $date): void
    {
        $holidays[] = match ($date->dayOfWeekIso) {
            6 => $date->addDays(2)->toDateString(),
            7 => $date->addDay()->toDateString(),
            default => $date->toDateString(),
        };
    }
}
