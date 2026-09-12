<?php

namespace App\Support;

use App\Models\TransportLedgerEntry;
use App\Models\TransportRun;
use Carbon\CarbonInterface;

/**
 * Transport is charged per leg, not per day.
 *
 *   morning collected + afternoon dropped  = £5.00
 *   one leg only                           = £2.50
 *   absent, or neither leg taken           = £0.00
 *
 * Rather than incrementally charging and refunding as outcomes change,
 * syncForDay() recomputes the day's charge from the runs themselves. It is
 * idempotent, so it is safe to call after any change — a leg being recorded,
 * changed, undone, or a member being marked absent — and always arrives at the
 * same answer for the same facts.
 *
 * Adjustments are audited rather than reversed, because the schema allows only
 * one charge row per member per day.
 */
final class TransportCharges
{
    /** Outcomes that mean the member actually travelled that leg. */
    public const CHARGEABLE = ['collected'];

    public static function syncForDay(int $memberId, CarbonInterface $date, ?int $userId = null): float
    {
        $expected = self::expectedFor($memberId, $date);
        $current = self::currentCharge($memberId, $date);

        if ($current && (float) $current->amount === $expected) {
            return $expected;
        }

        // There is a unique index (member_id, charge_date), so a day can hold
        // exactly one charge row. The charge is therefore adjusted in place
        // rather than reversed-and-reissued. The before/after is not lost:
        // TransportLedgerEntry is audited, so every adjustment lands in the
        // audit log with who changed it, when, and from what.
        if ($current) {
            if ($expected > 0) {
                $current->update([
                    'amount' => $expected,
                    'notes' => self::describe($memberId, $date),
                    'user_id' => $userId ?? $current->user_id,
                ]);
            } else {
                // Nothing travelled — remove the charge entirely rather than
                // leaving a £0.00 row that looks like a real journey.
                $current->delete();
            }

            return $expected;
        }

        if ($expected > 0) {
            TransportLedgerEntry::create([
                'member_id' => $memberId,
                'type' => 'charge',
                'amount' => $expected,
                'entry_date' => $date->toDateString(),
                'charge_date' => $date->toDateString(),
                'notes' => self::describe($memberId, $date),
                'user_id' => $userId,
            ]);
        }

        return $expected;
    }

    /**
     * What the day should cost, given the outcomes recorded against it.
     *
     * Only legs actually travelled are counted. Marking a member absent is not
     * a special case here: an absence means no leg was collected, so the total
     * falls to zero by itself. That matters for the awkward real case — driven
     * in at 9am, went home unwell at 10 — where the morning journey genuinely
     * happened and is still owed for, even though the member is absent.
     */
    public static function expectedFor(int $memberId, CarbonInterface $date): float
    {
        $legs = TransportRun::whereDate('run_date', $date)
            ->where('member_id', $memberId)
            ->whereIn('outcome', self::CHARGEABLE)
            ->count();

        return round($legs * TransportLedgerEntry::LEG_RATE, 2);
    }

    public static function currentCharge(int $memberId, CarbonInterface $date): ?TransportLedgerEntry
    {
        return TransportLedgerEntry::where('member_id', $memberId)
            ->where('type', 'charge')
            ->whereDate('entry_date', $date)
            ->whereDoesntHave('reversal')
            ->latest('id')
            ->first();
    }

    public static function chargedOn(int $memberId, CarbonInterface $date): float
    {
        return (float) (self::currentCharge($memberId, $date)?->amount ?? 0);
    }

    private static function describe(int $memberId, CarbonInterface $date): string
    {
        $phases = TransportRun::whereDate('run_date', $date)
            ->where('member_id', $memberId)
            ->whereIn('outcome', self::CHARGEABLE)
            ->pluck('phase')
            ->sort()
            ->implode(' + ');

        return 'Transport — '.($phases ?: 'no legs');
    }

}
