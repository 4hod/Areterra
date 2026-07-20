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
        $member->load('settings.keyWorker');

        return Inertia::render('Sar', [
            'generated_at' => now()->format('j F Y, H:i'),
            'member' => [
                'name' => trim("{$member->first_name} {$member->last_name}"),
                'preferred_name' => $member->preferred_name,
                'status' => $member->status,
                'dob' => $member->dob?->format('j F Y'),
                'nhs_number' => $member->nhs_number,
                'support_needs' => $member->support_needs,
                'diagnoses' => $member->diagnoses,
                'emergency_contacts' => $member->emergency_contacts ?? [],
                'phone' => $member->phone,
                'email' => $member->email,
                'address' => collect([$member->address_line1, $member->address_line2, $member->town, $member->postcode])->filter()->implode(', '),
                'key_worker' => $member->settings?->keyWorker?->name,
                'attendance_days' => $member->settings?->attendance_days ?? [],
                'transport_required' => (bool) $member->settings?->transport_required,
            ],
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
