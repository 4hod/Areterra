<?php

namespace App\Support;

use Carbon\CarbonInterface;

// Areterra operates Monday, Tuesday, Thursday and Friday (ISO 1, 2, 4, 5).
// One place to change if the charity ever opens Wednesdays.
class OperatingDays
{
    public const DAYS = [1, 2, 4, 5];

    public static function isOperatingDay(CarbonInterface $date): bool
    {
        return in_array($date->isoWeekday(), self::DAYS, true);
    }
}
