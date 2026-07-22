<?php

namespace App\Http\Controllers;

use App\Models\FundingOpportunity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class FundingController extends Controller
{
    public function index()
    {
        return Inertia::render('Funding', [
            'entries' => FundingOpportunity::orderBy('deadline')->get()
                ->map(fn ($f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'funder' => $f->funder,
                    'amount' => $f->amount !== null ? (float) $f->amount : null,
                    'deadline' => $f->deadline?->toDateString(),
                    'status' => $f->status,
                    'link' => $f->link,
                    'notes' => $f->notes,
                ]),
            'canManage' => Gate::allows('manage_operations'),
        ]);
    }

    public function store(Request $request)
    {
        FundingOpportunity::create([
            ...$request->validate([
                'title' => ['required', 'string', 'max:200'],
                'funder' => ['nullable', 'string', 'max:200'],
                'amount' => ['nullable', 'numeric', 'min:0'],
                'deadline' => ['nullable', 'date'],
                'status' => ['required', 'in:'.implode(',', FundingOpportunity::STATUSES)],
                'link' => ['nullable', 'url', 'max:500'],
                'notes' => ['nullable', 'string'],
            ]),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Funding opportunity added.');
    }

    public function update(Request $request, FundingOpportunity $funding)
    {
        $funding->update($request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'funder' => ['nullable', 'string', 'max:200'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:'.implode(',', FundingOpportunity::STATUSES)],
            'link' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Funding opportunity updated.');
    }

    public function destroy(FundingOpportunity $funding)
    {
        $funding->delete();

        return back()->with('success', "\"{$funding->title}\" removed.");
    }
}
