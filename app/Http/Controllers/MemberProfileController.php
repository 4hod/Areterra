<?php

namespace App\Http\Controllers;

use App\Models\CommsLog;
use App\Models\Member;
use App\Models\MemberAlert;
use App\Models\MemberConsent;
use App\Models\MemberContact;
use App\Models\MemberGoal;
use Illuminate\Http\Request;

// Writes for the member profile tabs: Comms, Goals, Outcomes, Alerts,
// Circle of Care, Consents, and photo upload.
class MemberProfileController extends Controller
{
    public function storePhoto(Request $request, Member $member)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('photo')->store('member-photos', 'public');
        $member->update(['photo_path' => '/storage/'.$path]);

        return back()->with('success', 'Photo updated.');
    }

    public function storeComms(Request $request, Member $member)
    {
        $member->commsLog()->create([
            ...$request->validate([
                'type' => ['required', 'in:'.implode(',', CommsLog::TYPES)],
                'direction' => ['required', 'in:inbound,outbound,both'],
                'subject' => ['nullable', 'string', 'max:200'],
                'summary' => ['nullable', 'string'],
                'contact_name' => ['nullable', 'string', 'max:100'],
                'organisation' => ['nullable', 'string', 'max:200'],
                'date' => ['required', 'date'],
            ]),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Communication logged.');
    }

    public function destroyComms(Member $member, CommsLog $comms)
    {
        abort_unless($comms->member_id === $member->id, 404);
        $comms->delete();

        return back()->with('success', 'Entry removed.');
    }

    public function storeContact(Request $request, Member $member)
    {
        $member->contacts()->create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'organisation' => ['nullable', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Contact added to circle of care.');
    }

    public function destroyContact(Member $member, MemberContact $contact)
    {
        abort_unless($contact->member_id === $member->id, 404);
        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }

    public function storeGoal(Request $request, Member $member)
    {
        $member->goals()->create($request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'target_date' => ['nullable', 'date'],
        ]));

        return back()->with('success', 'Goal added.');
    }

    public function updateGoal(Request $request, Member $member, MemberGoal $goal)
    {
        abort_unless($goal->member_id === $member->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:active,achieved,paused'],
        ]);

        $goal->update([
            ...$data,
            'achieved_at' => $data['status'] === 'achieved' ? today() : null,
        ]);

        return back()->with('success', 'Goal updated.');
    }

    public function storeOutcome(Request $request, Member $member)
    {
        $member->outcomes()->create([
            ...$request->validate([
                'date' => ['required', 'date'],
                'outcome' => ['required', 'string'],
                'member_goal_id' => ['nullable', 'exists:member_goals,id'],
            ]),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Outcome recorded.');
    }

    public function storeAlert(Request $request, Member $member)
    {
        $member->alerts()->create($request->validate([
            'type' => ['required', 'in:allergy,medical,behaviour,dietary,other'],
            'text' => ['required', 'string'],
            'severity' => ['required', 'in:amber,red'],
        ]));

        return back()->with('success', 'Alert added.');
    }

    public function destroyAlert(Member $member, MemberAlert $alert)
    {
        abort_unless($alert->member_id === $member->id, 404);
        $alert->delete();

        return back()->with('success', 'Alert removed.');
    }

    public function storeConsent(Request $request, Member $member)
    {
        $data = $request->validate([
            'consent_type' => ['required', 'in:'.implode(',', MemberConsent::TYPES)],
            'granted' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        MemberConsent::updateOrCreate(
            ['member_id' => $member->id, 'consent_type' => $data['consent_type']],
            [...$data, 'recorded_on' => today()],
        );

        return back()->with('success', 'Consent recorded.');
    }
}
