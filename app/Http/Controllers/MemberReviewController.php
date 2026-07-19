<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MemberReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MemberReviewController extends Controller
{
    public function index()
    {
        $latest = MemberReview::orderByDesc('review_date')->get()->unique('member_id')->keyBy('member_id');

        return Inertia::render('Reviews', [
            'members' => Member::active()->orderBy('first_name')->get()->map(function ($m) use ($latest) {
                $review = $latest->get($m->id);
                $next = $review?->next_review_date;

                return [
                    'id' => $m->id,
                    'name' => $m->displayName(),
                    'last_review' => $review?->review_date->toDateString(),
                    'next_review' => $next?->toDateString(),
                    'overdue' => $next === null || $next->lt(today()),
                ];
            }),
            'reviews' => MemberReview::with(['member', 'conductedBy:id,name'])
                ->orderByDesc('review_date')
                ->limit(30)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'member' => $r->member->displayName(),
                    'member_id' => $r->member_id,
                    'review_date' => $r->review_date->toDateString(),
                    'outcomes' => $r->outcomes,
                    'actions' => $r->actions,
                    'next_review_date' => $r->next_review_date?->toDateString(),
                    'conducted_by' => $r->conductedBy->name,
                ]),
            'canManage' => Gate::allows('edit_members'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'review_date' => ['required', 'date'],
            'outcomes' => ['nullable', 'string'],
            'actions' => ['nullable', 'string'],
            'next_review_date' => ['nullable', 'date', 'after:review_date'],
        ]);

        MemberReview::create([...$data, 'conducted_by' => $request->user()->id]);

        return back()->with('success', 'Review recorded.');
    }
}
