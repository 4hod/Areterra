<?php

namespace App\Support;

use App\Models\Member;
use App\Models\TransportLedgerEntry;

/**
 * Prepayment for transport.
 *
 * Members pay in advance — a tenner on Tuesday, twenty from Billy on Monday —
 * and travel it off leg by leg. The ledger already recorded this; what was
 * missing was any way to see how far a payment stretches, or to know before
 * someone runs out.
 *
 * Balance convention: payments are positive, charges negative, so a positive
 * balance is credit and a negative balance is owed.
 */
final class TransportCredit
{
    /** Warn once the member has fewer than this many legs left. */
    public const LOW_LEGS = 2;

    /** Offered as quick top-up buttons — the amounts people actually hand over. */
    public const SUGGESTED = [5.00, 10.00, 15.00, 20.00];

    public static function balance(int $memberId): float
    {
        return round(TransportLedgerEntry::balanceFor($memberId), 2);
    }

    /** How many single legs the current credit still covers. */
    public static function legsRemaining(int $memberId): int
    {
        $balance = self::balance($memberId);

        return $balance <= 0 ? 0 : (int) floor($balance / TransportLedgerEntry::LEG_RATE);
    }

    /** Whole return journeys remaining — what staff usually think in. */
    public static function returnsRemaining(int $memberId): int
    {
        return intdiv(self::legsRemaining($memberId), 2);
    }

    public static function isLow(int $memberId): bool
    {
        return self::legsRemaining($memberId) <= self::LOW_LEGS;
    }

    public static function owes(int $memberId): bool
    {
        return self::balance($memberId) < 0;
    }

    /**
     * How many days of travel this member's credit covers, given the days they
     * actually attend. Someone in Tuesday and Thursday burns £5 a week; someone
     * in most days burns it far faster, and £20 means something different to
     * each of them.
     */
    public static function daysCovered(Member $member): ?int
    {
        $perWeek = self::scheduledDaysPerWeek($member);

        if ($perWeek === 0) {
            return null;
        }

        return intdiv(self::legsRemaining($member->id), 2);
    }

    /** A sensible top-up to suggest: enough to clear any debt and cover a fortnight. */
    public static function suggestedTopUp(Member $member): float
    {
        $perWeek = self::scheduledDaysPerWeek($member) ?: 2;
        $fortnight = $perWeek * 2 * TransportLedgerEntry::DAILY_RATE;
        $needed = $fortnight - self::balance($member->id);

        if ($needed <= 0) {
            return 0.0;
        }

        // Round up to the nearest £5 — people pay in notes and coins, not pennies.
        return (float) (ceil($needed / 5) * 5);
    }

    /** Running statement, newest first, with the balance after each movement. */
    public static function statement(int $memberId, int $limit = 30): array
    {
        $entries = TransportLedgerEntry::where('member_id', $memberId)
            ->orderBy('entry_date')->orderBy('id')
            ->get();

        $running = 0.0;
        $rows = [];

        foreach ($entries as $entry) {
            $signed = $entry->type === 'payment' ? (float) $entry->amount : -1 * (float) $entry->amount;
            $running = round($running + $signed, 2);

            $rows[] = [
                'id' => $entry->id,
                'date' => $entry->entry_date->toDateString(),
                'type' => $entry->type,
                'amount' => $signed,
                'balance_after' => $running,
                'notes' => $entry->notes,
                'reversed' => $entry->reversal()->exists(),
            ];
        }

        return array_slice(array_reverse($rows), 0, $limit);
    }

    private static function scheduledDaysPerWeek(Member $member): int
    {
        // attendance_days is a JSON array of ISO day numbers, e.g. [2, 4] for
        // a member in on Tuesdays and Thursdays.
        $days = $member->settings?->attendance_days;

        return is_array($days) ? count($days) : 0;
    }
}
