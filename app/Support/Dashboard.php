<?php

namespace App\Support;

use App\Models\LedgerEntry;
use App\Models\MemberInvoice;
use App\Models\PayrollPeriod;
use App\Models\Task;

/**
 * Point 18 — assembled from the whole platform, not a wall of per-module
 * widgets. Answers "what needs me today?" and nothing else.
 */
final class Dashboard
{
    public static function build(): array
    {
        $period = Period::current();

        return [
            'period' => $period->toArray(),
            'needs_attention' => DueScanner::scanThrottled(14),
            'open_tasks' => Task::query()->open()->count(),
            'overdue_tasks' => Task::query()->overdue()->count(),
            'unpaid_invoices' => [
                'count' => MemberInvoice::query()->where('status', '!=', 'paid')->count(),
                'value' => round((float) MemberInvoice::query()->where('status', '!=', 'paid')->sum('amount'), 2),
            ],
            'finance' => Ledger::summaryFor($period),
            'costs_by_category' => Ledger::byCategory($period),
            'payroll' => [
                'draft_periods' => PayrollPeriod::query()->where('status', 'draft')->count(),
                'awaiting_approval' => PayrollPeriod::query()->where('status', 'draft')
                    ->get()
                    ->filter(fn ($p) => \App\Workflows\Payroll\PayrollPreview::for($p)->problems() === [])
                    ->count(),
            ],
            'restricted_funds' => round(
                (float) LedgerEntry::query()->effective()->where('restricted', true)->where('direction', 'income')->sum('amount')
                - (float) LedgerEntry::query()->effective()->where('restricted', true)->where('direction', 'expense')->sum('amount'),
                2,
            ),
        ];
    }
}
