<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Inertia\Inertia;

// Subject Access Request: a full printable data extract for one member,
// pulling across every domain that stores data about them.
class SarController extends Controller
{
    public function show(Member $member)
    {
        $member->load([
            'settings.keyWorker',
            'contacts',
            'consents',
            'commsLog.user',
            'goals',
            'outcomes.goal',
            'outcomes.user',
            'alerts',
            'bodyMaps.user',
        ]);

        return Inertia::render('Sar', [
            'generated_at' => now()->format('j F Y, H:i'),
            'member' => [
                'name' => trim("{$member->first_name} {$member->last_name}"),
                'preferred_name' => $member->preferred_name,
                'status' => $member->status,
                'dob' => $member->dob?->format('j F Y'),
                'gender' => $member->gender,
                'nhs_number' => $member->nhs_number,
                'support_needs' => $member->support_needs,
                'medical_notes' => $member->medical_notes,
                'interests' => $member->interests,
                'diagnoses' => $member->diagnoses,
                'medication' => $member->medication,
                'allergies' => $member->allergies,
                'emergency_contacts' => $member->emergency_contacts ?? [],
                'phone' => $member->phone,
                'email' => $member->email,
                'address' => collect([$member->address_line1, $member->address_line2, $member->town, $member->postcode])->filter()->implode(', '),
                'key_worker' => $member->settings?->keyWorker?->name,
                'attendance_days' => $member->settings?->attendance_days ?? [],
                'transport_required' => (bool) $member->settings?->transport_required,
                'gp_name' => $member->gp_name,
                'gp_practice' => $member->gp_practice,
                'gp_phone' => $member->gp_phone,
            ],
            'contacts' => $member->contacts->map(fn ($contact) => [
                'name' => $contact->name,
                'role' => $contact->role,
                'organisation' => $contact->organisation,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'notes' => $contact->notes,
            ]),
            'consents' => $member->consents->map(fn ($consent) => [
                'type' => $consent->consent_type,
                'granted' => $consent->granted,
                'recorded_on' => $consent->recorded_on?->toDateString(),
                'expires_at' => $consent->expiresAt()?->toDateString(),
                'notes' => $consent->notes,
            ]),
            'communications' => $member->commsLog->sortByDesc('date')->values()->map(fn ($communication) => [
                'date' => $communication->date?->toDateString(),
                'type' => $communication->type,
                'direction' => $communication->direction,
                'subject' => $communication->subject,
                'summary' => $communication->summary,
                'contact_name' => $communication->contact_name,
                'organisation' => $communication->organisation,
                'recorded_by' => $communication->user?->name,
            ]),
            'goals' => $member->goals->sortByDesc('created_at')->values()->map(fn ($goal) => [
                'title' => $goal->title,
                'description' => $goal->description,
                'status' => $goal->status,
                'target_date' => $goal->target_date?->toDateString(),
                'achieved_at' => $goal->achieved_at?->toDateString(),
            ]),
            'outcomes' => $member->outcomes->sortByDesc('date')->values()->map(fn ($outcome) => [
                'date' => $outcome->date?->toDateString(),
                'outcome' => $outcome->outcome,
                'goal' => $outcome->goal?->title,
                'recorded_by' => $outcome->user?->name,
            ]),
            'alerts' => $member->alerts->map(fn ($alert) => [
                'type' => $alert->type,
                'severity' => $alert->severity,
                'text' => $alert->text,
            ]),
            'bodyMaps' => $member->bodyMaps->sortByDesc('recorded_at')->values()->map(fn ($bodyMap) => [
                'recorded_at' => $bodyMap->recorded_at?->toDateTimeString(),
                'markers' => $bodyMap->markers,
                'notes' => $bodyMap->notes,
                'recorded_by' => $bodyMap->user?->name,
            ]),
            'attendance' => $member->attendances()->orderByDesc('date')->get()
                ->map(fn ($a) => ['date' => $a->date->toDateString(), 'checked_in' => $a->checked_in, 'arrival_mood' => $a->arrival_mood, 'notes' => $a->notes]),
            'endOfDay' => $member->endOfDayRecords()->orderByDesc('date')->get()
                ->map(fn ($r) => ['date' => $r->date->toDateString(), 'arrival_mood' => $r->arrival_mood, 'end_mood' => $r->end_mood, 'session_type' => $r->session_type, 'activities' => $r->activities, 'notes' => $r->notes, 'concern' => $r->concern]),
            'reviews' => $member->reviews()->orderByDesc('review_date')->get()
                ->map(fn ($r) => ['date' => $r->review_date->toDateString(), 'outcomes' => $r->outcomes, 'actions' => $r->actions]),
            'abc' => $member->abcObservations()->orderByDesc('observed_at')->get()
                ->map(fn ($o) => ['date' => $o->observed_at->toDateTimeString(), 'antecedent' => $o->antecedent, 'behaviour' => $o->behaviour, 'consequence' => $o->consequence, 'wellbeing_score' => $o->wellbeing_score]),
            'transportLedger' => $member->transportLedger()->orderByDesc('entry_date')->get()
                ->map(fn ($t) => ['date' => $t->entry_date->toDateString(), 'type' => $t->type, 'amount' => (float) $t->amount]),
        ]);
    }
}
