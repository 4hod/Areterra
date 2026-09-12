<?php

namespace App\Http\Controllers;

use App\Events\SafeguardingConcernRaised;

use App\Models\Member;
use App\Models\SafeguardingConcern;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Gated by can:access_safeguarding + password.confirm (re-verification).
class SafeguardingController extends Controller
{
    public function index()
    {
        return Inertia::render('Safeguarding', [
            'concerns' => SafeguardingConcern::with(['member', 'reporter:id,name'])
                ->orderByDesc('date')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'member' => $c->member?->displayName(),
                    'reporter' => $c->reporter->name,
                    'source' => $c->source,
                    'date' => $c->date->toDateString(),
                    'details' => $c->details,
                    'actions_taken' => $c->actions_taken,
                    'status' => $c->status,
                    'closed_at' => $c->closed_at?->toDateString(),
                ]),
            'members' => Member::orderBy('first_name')->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()]),
        ]);
    }

    public function store(Request $request)
    {
        $concern = SafeguardingConcern::create([
            ...$request->validate([
                'member_id' => ['nullable', 'exists:members,id'],
                'date' => ['required', 'date'],
                'details' => ['required', 'string'],
                'actions_taken' => ['nullable', 'string'],
            ]),
            'reported_by' => $request->user()->id,
            'source' => 'manual',
        ]);

        SafeguardingConcernRaised::dispatch($concern);

        return back()->with('success', 'Safeguarding concern logged.');
    }

    public function update(Request $request, SafeguardingConcern $concern)
    {
        $data = $request->validate([
            'actions_taken' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:open,closed'],
        ]);

        if (($data['status'] ?? null) === 'closed' && $concern->status !== 'closed') {
            $data['closed_at'] = now();
        }

        $concern->update($data);

        return back()->with('success', 'Concern updated.');
    }
}
