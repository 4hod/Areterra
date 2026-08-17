<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\SarRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SarRequestController extends Controller
{
    public function index()
    {
        return Inertia::render('SarRequests', [
            'requests' => SarRequest::with('member:id,first_name,last_name,preferred_name')
                ->orderByRaw("status = 'fulfilled', status = 'declined'")
                ->orderBy('deadline_date')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'requester_name' => $r->requester_name,
                    'requester_relationship' => $r->requester_relationship,
                    'member' => $r->member?->displayName(),
                    'received_date' => $r->received_date->toDateString(),
                    'deadline_date' => $r->deadline_date->toDateString(),
                    'status' => $r->status,
                    'fulfilled_date' => $r->fulfilled_date?->toDateString(),
                    'notes' => $r->notes,
                    'is_overdue' => $r->isOverdue(),
                ]),
            'members' => Member::orderBy('first_name')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'requester_name' => ['required', 'string', 'max:150'],
            'requester_relationship' => ['nullable', 'string', 'max:100'],
            'member_id' => ['nullable', 'exists:members,id'],
            'received_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        SarRequest::create([
            ...$data,
            'deadline_date' => \Carbon\Carbon::parse($data['received_date'])->addMonth(),
            'status' => 'pending',
            'logged_by' => $request->user()->id,
        ]);

        return back()->with('success', 'SAR request logged. Deadline set to one calendar month from receipt.');
    }

    public function update(Request $request, SarRequest $sarRequest)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', SarRequest::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        if ($data['status'] === 'fulfilled' && ! $sarRequest->fulfilled_date) {
            $data['fulfilled_date'] = today();
        }

        $sarRequest->update($data);

        return back()->with('success', 'SAR request updated.');
    }
}
