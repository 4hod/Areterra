<?php

namespace App\Models\Concerns;

use App\Models\LedgerEntry;

/** The inverse of Ledger::post() — see what this record cost or earned. */
trait HasLedgerEntries
{
    public function ledgerEntries()
    {
        return $this->morphMany(LedgerEntry::class, 'source')->latest('entry_date');
    }

    public function ledgerTotal(): float
    {
        return round((float) $this->ledgerEntries()->whereNull('reverses_id')->sum('amount'), 2);
    }
}
