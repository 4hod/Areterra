<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Support\PrivateMedia;
use Inertia\Inertia;

class MemberPassportController extends Controller
{
    public function show(Member $member)
    {
        $member->load('settings.keyWorker');
        $today = $member->attendances()->whereDate('date', today())->first();

        return Inertia::render('Members/Passport', [
            'member' => [
                'id' => $member->id,
                'name' => $member->displayName(),
                'preferred_name' => $member->preferred_name,
                'photo_path' => PrivateMedia::memberPhotoUrl($member),
                'support_needs' => $member->support_needs,
                'interests' => $member->interests,
                'allergies' => $member->allergies,
                'medication' => $member->medication,
                'medical_notes' => $member->medical_notes,
                'emergency_contacts' => $member->emergency_contacts ?? [],
                'key_worker' => $member->settings?->keyWorker?->name,
                'transport_required' => (bool) $member->settings?->transport_required,
            ],
            'today' => [
                'status' => $today?->status ?? 'expected',
                'checked_in' => (bool) $today?->checked_in,
                'arrival_mood' => $today?->arrival_mood,
                'notes' => $today?->notes,
            ],
            'alerts' => $member->alerts()->orderByRaw("case when severity = 'red' then 0 else 1 end")->get()
                ->map(fn ($alert) => $alert->only(['id', 'type', 'text', 'severity'])),
            'goals' => $member->goals()->where('status', 'active')->latest()->limit(3)->get()
                ->map(fn ($goal) => $goal->only(['id', 'title', 'description'])),
            'recentHandover' => $member->endOfDayRecords()->orderByDesc('date')->first()?->only([
                'date', 'end_mood', 'notes', 'concern', 'concern_detail',
            ]),
        ]);
    }
}
