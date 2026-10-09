<?php

namespace App\Http\Controllers;

use App\Events\InvoicePaid;

use App\Models\Member;
use App\Models\MemberInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Ledger;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = MemberInvoice::with('member')->orderByDesc('invoice_date')->get();

        return Inertia::render('Invoices', [
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->id,
                'member' => $i->member->displayName(),
                'qb_reference' => $i->qb_reference,
                'amount' => (float) $i->amount,
                'invoice_date' => $i->invoice_date->toDateString(),
                'due_date' => $i->due_date?->toDateString(),
                'paid_date' => $i->paid_date?->toDateString(),
                'status' => $i->effectiveStatus(),
                'qb_url' => $i->qb_url,
            ]),
            'summary' => [
                'outstanding' => (float) $invoices->filter(fn ($i) => in_array($i->effectiveStatus(), ['sent', 'overdue']))->sum('amount'),
                'overdueCount' => $invoices->filter(fn ($i) => $i->effectiveStatus() === 'overdue')->count(),
                'collected' => (float) $invoices->where('status', 'paid')->sum('amount'),
            ],
            'members' => Member::active()->orderBy('first_name')->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()]),
        ]);
    }

    public function store(Request $request)
    {
        $invoice = MemberInvoice::create($request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'qb_reference' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,sent,paid,cancelled'],
            'qb_url' => ['nullable', 'url', 'max:500'],
        ]));

        if ($invoice->status === 'paid') {
            $invoice->update(['paid_date' => today()]);
            InvoicePaid::dispatch($invoice->fresh());
        }

        return back()->with('success', 'Invoice tracked.');
    }

    public function markPaid(MemberInvoice $invoice)
    {
        $this->markInvoicePaid($invoice);

        return back()->with('success', "{$invoice->qb_reference} marked paid.");
    }

    public function bulkMarkPaid(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['exists:member_invoices,id'],
        ]);

        $invoices = MemberInvoice::whereIn('id', $data['ids'])->get();
        DB::transaction(fn () => $invoices->each(fn (MemberInvoice $invoice) => $this->markInvoicePaid($invoice)));
        $count = $invoices->count();

        return back()->with('success', "{$count} invoice".($count === 1 ? '' : 's').' marked paid.');
    }

    public function update(Request $request, MemberInvoice $invoice)
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:draft,sent,paid,overdue,cancelled'],
            'due_date' => ['nullable', 'date'],
            'qb_url' => ['nullable', 'url', 'max:500'],
        ]);
        $wasPaid = $invoice->status === 'paid';

        DB::transaction(function () use ($invoice, $data, $wasPaid) {
            $invoice->update($data);

            if ($wasPaid && $invoice->status !== 'paid') {
                $this->reverseInvoiceIncome($invoice, "Invoice {$invoice->qb_reference} changed from paid to {$invoice->status}.");
                $invoice->update(['paid_date' => null]);
            }

            if (! $wasPaid && $invoice->status === 'paid') {
                $invoice->update(['paid_date' => $invoice->paid_date ?? today()]);
                InvoicePaid::dispatch($invoice->fresh());
            }
        });

        return back()->with('success', 'Invoice updated.');
    }

    public function destroy(MemberInvoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            if ($invoice->status === 'paid') {
                $this->reverseInvoiceIncome($invoice, "Paid invoice {$invoice->qb_reference} was deleted.");
            }

            $invoice->delete();
        });

        return back()->with('success', "{$invoice->qb_reference} removed.");
    }

    private function markInvoicePaid(MemberInvoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        $invoice->update(['status' => 'paid', 'paid_date' => today()]);
        InvoicePaid::dispatch($invoice->fresh());
    }

    private function reverseInvoiceIncome(MemberInvoice $invoice, string $reason): void
    {
        $entry = $invoice->ledgerEntries()->where('category', 'member_fees')->effective()->first();

        if ($entry) {
            Ledger::reverse($entry, $reason);
        }
    }
}
