<?php

namespace App\Support;

use App\Models\Grant;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Point 4 — the single way anything lands in Finance.
 *
 * Nothing writes to ledger_entries directly. Costs are posted from where they
 * originate (payroll, vet records, maintenance, grants), so Finance aggregates
 * rather than being typed into.
 *
 * Point 17: post() is idempotent per source and reverse() is non-destructive —
 * a correction adds a mirror entry, it never edits or deletes history.
 */
final class Ledger
{
    public static function post(
        Model $source,
        string $direction,
        string $category,
        string $description,
        float $amount,
        string $date,
        ?Grant $grant = null,
    ): LedgerEntry {
        // Re-approving a pay run must not double-post it.
        $existing = LedgerEntry::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('category', $category)
            ->first();

        if ($existing) {
            $existing->update([
                'amount' => round($amount, 2),
                'description' => $description,
                'entry_date' => $date,
            ]);

            return $existing;
        }

        return LedgerEntry::create([
            'entry_date' => $date,
            'direction' => $direction,
            'category' => $category,
            'description' => $description,
            'amount' => round($amount, 2),
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'grant_id' => $grant?->id,
            'restricted' => $grant !== null,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Correction by reversal — the original row stays exactly as it was.
     * Managers only: reversing money is not the same as entering it.
     */
    public static function reverse(LedgerEntry $entry, string $reason): LedgerEntry
    {
        if (auth()->check() && ! auth()->user()->hasCapability('manage_finance')) {
            throw new \App\Exceptions\WorkflowException(
                ['Reversing a finance entry needs manager approval.'],
                'You cannot reverse this entry.',
                ['ledger_entry_id' => $entry->id],
            );
        }

        return LedgerEntry::create([
            'entry_date' => today()->toDateString(),
            'direction' => $entry->direction,
            'category' => $entry->category,
            'description' => 'Reversal: '.$entry->description,
            'amount' => -1 * (float) $entry->amount,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'grant_id' => $entry->grant_id,
            'restricted' => $entry->restricted,
            'reverses_id' => $entry->id,
            'reversal_reason' => $reason,
            'created_by' => auth()->id(),
        ]);
    }

    /** @return array<string, float> */
    public static function summaryFor(Period $period): array
    {
        $rows = LedgerEntry::query()->effective()->inPeriod($period)->get();

        $income = (float) $rows->where('direction', 'income')->sum('amount');
        $expense = (float) $rows->where('direction', 'expense')->sum('amount');
        $restrictedSpend = (float) $rows->where('direction', 'expense')->where('restricted', true)->sum('amount');

        return [
            'period' => $period->label(),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            // Restricted money is not free reserve — kept separate deliberately.
            'unrestricted_net' => round(
                (float) $rows->where('direction', 'income')->where('restricted', false)->sum('amount')
                - ((float) $rows->where('direction', 'expense')->where('restricted', false)->sum('amount')),
                2,
            ),
            'restricted_spend' => round($restrictedSpend, 2),
        ];
    }

    /** @return array<string, float> */
    public static function byCategory(Period $period): array
    {
        return LedgerEntry::query()->effective()->inPeriod($period)
            ->get()
            ->groupBy('category')
            ->map(fn ($rows) => round((float) $rows->sum(fn ($r) => $r->direction === 'expense' ? $r->amount : -$r->amount), 2))
            ->sortDesc()
            ->all();
    }
}
