<?php

namespace App\Support;

use App\Models\PayrollRate;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves an hourly rate for a payee *as at a given date*.
 *
 * The models previously answered "what is this person's rate today?" via
 * currentRate(). Payroll doesn't want today's rate — it wants the rate that
 * applied during the period being run. Reprocessing a period from three months
 * ago after somebody got a pay rise silently produced the new rate against the
 * old hours.
 *
 * One rule, one place, used by every payroll path.
 */
final class PayrollRates
{
    /**
     * @param  Model  $payable  A User or StaffRosterMember
     */
    public static function asAt(Model $payable, CarbonInterface|string $date): ?float
    {
        $date = CarbonImmutable::parse($date)->toDateString();

        $rate = PayrollRate::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        return $rate ? (float) $rate->hourly_rate : null;
    }

    /** Contracted hours in force on $date, if recorded alongside the rate. */
    public static function contractedHoursAsAt(Model $payable, CarbonInterface|string $date): ?float
    {
        $date = CarbonImmutable::parse($date)->toDateString();

        $rate = PayrollRate::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->first();

        return $rate?->contracted_hours !== null ? (float) $rate->contracted_hours : null;
    }

    /**
     * True when the payee has no rate at all on or before $date — the condition
     * that used to be swallowed by `?? 0`.
     */
    public static function missing(Model $payable, CarbonInterface|string $date): bool
    {
        return self::asAt($payable, $date) === null;
    }
}
