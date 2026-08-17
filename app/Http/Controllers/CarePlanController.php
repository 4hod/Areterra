<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Inertia\Inertia;

// Printable one-page care plan: who they are, their typical week, support
// needs, medical/emergency info, current goals — the thing you'd hand to a
// new staff member, or take to a family/social worker meeting.
class CarePlanController extends Controller
{
    private const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function show(Member $member)
    {
        $member->load('settings.keyWorker', 'goals');

        return Inertia::render('CarePlan', [
            'generated_at' => now()->format('j F Y'),
            'member' => [
                'name' => $member->displayName(),
                'dob' => $member->dob?->format('j F Y'),
                'photo_path' => $member->photo_path,
                'support_needs' => $member->support_needs,
                'diagnoses' => $member->diagnoses,
                'medication' => $member->medication,
                'emergency_contacts' => $member->emergency_contacts ?? [],
                'key_worker' => $member->settings?->keyWorker?->name,
                'transport_required' => (bool) $member->settings?->transport_required,
                'typical_week' => collect($member->settings?->attendance_days ?? [])
                    ->sort()
                    ->map(fn ($day) => self::DAY_NAMES[$day] ?? $day)
                    ->values(),
            ],
            'goals' => $member->goals()->where('status', 'active')->get()
                ->map(fn ($g) => ['title' => $g->title, 'description' => $g->description]),
        ]);
    }
}
