<?php

namespace App\Http\Controllers;

use App\Models\ComplianceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ComplianceController extends Controller
{
    public function index()
    {
        $items = ComplianceItem::orderBy('due_date')->get();

        return Inertia::render('Compliance', [
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'title' => $i->title,
                'category' => $i->category,
                'due_date' => $i->due_date->toDateString(),
                'notes' => $i->notes,
                'completed_at' => $i->completed_at?->toDateString(),
                'overdue' => $i->isOverdue(),
            ]),
            'summary' => [
                'overdue' => $items->filter(fn ($i) => $i->isOverdue())->count(),
                'dueSoon' => $items->filter(fn ($i) => ! $i->completed_at && ! $i->isOverdue() && $i->due_date->lte(today()->addDays(30)))->count(),
                'complete' => $items->whereNotNull('completed_at')->count(),
            ],
            'canManage' => Gate::allows('manage_compliance'),
        ]);
    }

    public function store(Request $request)
    {
        ComplianceItem::create($request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Compliance item added.');
    }

    public function complete(Request $request, ComplianceItem $item)
    {
        $item->update([
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return back()->with('success', "\"{$item->title}\" marked complete.");
    }
}
