<?php

namespace App\Listeners;

use App\Events\GrantExpenseRecorded;
use App\Support\Ledger;

/** Restricted spend is posted against its grant so reserves stay honest. */
class PostGrantExpenseToLedger
{
    public function handle(GrantExpenseRecorded $event): void
    {
        $expenditure = $event->expenditure;

        Ledger::post(
            source: $expenditure,
            direction: 'expense',
            category: 'grant_spend',
            description: $expenditure->description,
            amount: (float) $expenditure->amount,
            date: $expenditure->spent_date->toDateString(),
            grant: $expenditure->grant,
        );
    }
}
