<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Animal;
use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImpactEntryController extends Controller
{
    public function store(Request $request, Member $member)
    {
        abort_if($member->status === 'archived', 422, 'Archived member records are read-only.');

        $data = $request->validate([
            'observed_at' => ['required', 'date'],
            'member_goal_id' => ['nullable', Rule::exists('member_goals', 'id')->where('member_id', $member->id)],
            'activity_id' => ['nullable', Rule::exists(Activity::class, 'id')],
            'animal_id' => ['nullable', Rule::exists(Animal::class, 'id')],
            'mood_before' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'mood_after' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'engagement_rating' => ['nullable', 'integer', 'between:1,5'],
            'independence_rating' => ['nullable', 'integer', 'between:1,5'],
            'outcome_note' => ['required', 'string', 'max:5000'],
            'evidence_tags' => ['nullable', 'array'],
            'evidence_tags.*' => ['string', 'max:50'],
        ]);

        $member->impactEntries()->create([...$data, 'recorded_by' => $request->user()->id]);

        return back()->with('success', 'Impact evidence recorded.');
    }
}
