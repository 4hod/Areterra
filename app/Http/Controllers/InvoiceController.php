<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberInvoice;
use Illuminate\Http\Request;
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
        MemberInvoice::create($request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'qb_reference' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,sent,paid,cancelled'],
            'qb_url' => ['nullable', 'url', 'max:500'],
        ]));

        return back()->with('success', 'Invoice tracked.');
    }

    public function markPaid(MemberInvoice $invoice)
    {
        $invoice->update(['status' => 'paid', 'paid_date' => today()]);

        return back()->with('success', "{$invoice->qb_reference} marked paid.");
    }

    public function update(Request $request, MemberInvoice $invoice)
    {
        $invoice->update($request->validate([
            'status' => ['sometimes', 'in:draft,sent,paid,overdue,cancelled'],
            'due_date' => ['nullable', 'date'],
            'qb_url' => ['nullable', 'url', 'max:500'],
        ]));

        return back()->with('success', 'Invoice updated.');
    }
}
