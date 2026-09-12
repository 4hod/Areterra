<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Support\Ledger;

class PostInvoicePaymentToLedger
{
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        Ledger::post(
            source: $invoice,
            direction: 'income',
            category: 'member_fees',
            description: 'Invoice '.($invoice->qb_reference ?? "#{$invoice->id}"),
            amount: (float) $invoice->amount,
            date: ($invoice->paid_date ?? today())->toDateString(),
        );
    }
}
