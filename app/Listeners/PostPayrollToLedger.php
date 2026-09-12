<?php

namespace App\Listeners;

use App\Events\PayrollApproved;
use App\Support\Ledger;

/**
 * Point 4 in one file: staff costs reach Finance because payroll was approved,
 * not because somebody retyped the total.
 */
class PostPayrollToLedger
{
    public function handle(PayrollApproved $event): void
    {
        $period = $event->period;
        $total = (float) $period->entries()->sum('total');

        if ($total <= 0) {
            return;
        }

        Ledger::post(
            source: $period,
            direction: 'expense',
            category: 'staff_costs',
            description: "Payroll — {$period->label}",
            amount: $total,
            date: ($period->pay_date ?? $period->end_date)->toDateString(),
        );
    }
}
