<?php

namespace App\Http\Controllers;

use App\Models\Grant;
use App\Models\InKindDonation;
use App\Models\MemberInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index()
    {
        $grants = Grant::with('expenditures')->orderByDesc('start_date')->get();
        $invoices = MemberInvoice::all();

        return Inertia::render('Finance', [
            'grants' => $grants->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'funder' => $g->funder,
                'amount' => (float) $g->amount,
                'spent' => $g->spent(),
                'start_date' => $g->start_date?->toDateString(),
                'end_date' => $g->end_date?->toDateString(),
                'status' => $g->status,
                'expenditures' => $g->expenditures->map(fn ($e) => [
                    'id' => $e->id,
                    'description' => $e->description,
                    'amount' => (float) $e->amount,
                    'spent_date' => $e->spent_date->toDateString(),
                ]),
            ]),
            'inKind' => InKindDonation::with('grant:id,title')->orderByDesc('date')->limit(30)->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'donor' => $d->donor,
                    'type' => $d->type,
                    'category' => $d->category,
                    'estimated_value' => (float) $d->estimated_value,
                    'quantity' => $d->quantity,
                    'date' => $d->date->toDateString(),
                    'grant' => $d->grant?->title,
                ]),
            'summary' => [
                'grantIncome' => (float) $grants->whereIn('status', ['active', 'completed'])->sum('amount'),
                'grantSpend' => (float) $grants->sum(fn ($g) => $g->spent()),
                'inKindValue' => (float) InKindDonation::sum('estimated_value'),
                'invoiced' => (float) $invoices->where('status', '!=', 'cancelled')->sum('amount'),
                'collected' => (float) $invoices->where('status', 'paid')->sum('amount'),
            ],
        ]);
    }

    public function storeGrant(Request $request)
    {
        Grant::create($request->validate([
            'title' => ['required', 'string', 'max:200'],
            'funder' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:applied,active,completed,declined'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Grant added.');
    }

    public function updateGrant(Request $request, Grant $grant)
    {
        $grant->update($request->validate([
            'status' => ['sometimes', 'in:applied,active,completed,declined'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Grant updated.');
    }

    public function storeExpenditure(Request $request, Grant $grant)
    {
        $grant->expenditures()->create([
            ...$request->validate([
                'description' => ['required', 'string', 'max:255'],
                'amount' => ['required', 'numeric', 'min:0.01'],
                'spent_date' => ['required', 'date'],
            ]),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Expenditure recorded.');
    }

    public function storeInKind(Request $request)
    {
        InKindDonation::create($request->validate([
            'donor' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'estimated_value' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'grant_id' => ['nullable', 'exists:grants,id'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'In-kind donation recorded.');
    }
}
