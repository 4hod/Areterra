<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Document;
use App\Models\FormSubmission;
use App\Models\Incident;
use App\Support\PrivateMedia;
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

        $incidents = Incident::with('reportedBy:id,name')->whereMorphedTo('subject', $member)->orderByDesc('occurred_at')->get();
        $documents = Document::with('uploader:id,name')->where(function ($query) use ($member, $incidents) {
            $query->whereMorphedTo('attachable', $member);
            if ($incidents->isNotEmpty()) {
                $query->orWhere(function ($linked) use ($incidents) {
                    $linked->where('attachable_type', (new Incident())->getMorphClass())
                        ->whereIn('attachable_id', $incidents->pluck('id'));
                });
            }
        })->get();

        $formSubmissions = FormSubmission::with(['form:id,title', 'submittedBy:id,name', 'data.field:id,label'])
            ->whereMorphedTo('subject', $member)->orderByDesc('submitted_at')->get();

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
                ->map(fn ($r) => [
                    'date' => $r->date->toDateString(), 'arrival_mood' => $r->arrival_mood,
                    'end_mood' => $r->end_mood, 'session_type' => $r->session_type,
                    'activities' => $r->activities, 'notes' => $r->notes, 'concern' => $r->concern,
                    'food_intake' => $r->food_intake, 'fluid_intake' => $r->fluid_intake,
                    'toileting_notes' => $r->toileting_notes, 'medication_given' => $r->medication_given,
                    'medication_notes' => $r->medication_notes, 'concern_detail' => $r->concern_detail,
                    'incident' => $r->incident, 'incident_detail' => $r->incident_detail,
                    'photos' => PrivateMedia::endOfDayPhotoUrls($member, $r),
                ]),
            'reviews' => $member->reviews()->orderByDesc('review_date')->get()
                ->map(fn ($r) => ['date' => $r->review_date->toDateString(), 'outcomes' => $r->outcomes, 'actions' => $r->actions]),
            'abc' => $member->abcObservations()->orderByDesc('observed_at')->get()
                ->map(fn ($o) => ['date' => $o->observed_at->toDateTimeString(), 'antecedent' => $o->antecedent, 'behaviour' => $o->behaviour, 'consequence' => $o->consequence, 'wellbeing_score' => $o->wellbeing_score]),
            'transportLedger' => $member->transportLedger()->orderByDesc('entry_date')->get()
                ->map(fn ($t) => ['date' => $t->entry_date->toDateString(), 'type' => $t->type, 'amount' => (float) $t->amount]),
            'additionalSections' => [
                'Staff notes' => $member->notes()->orderByDesc('noted_at')->get()->map(fn ($note) => [
                    'date' => $note->noted_at?->toDateTimeString(), 'type' => $note->note_type,
                    'note' => $note->note, 'author' => $note->author_name, 'source' => $note->source,
                ]),
                'Outcome evidence' => $member->impactEntries()->with(['goal:id,title', 'activity:id,title', 'animal:id,name', 'recorder:id,name'])->orderByDesc('observed_at')->get()->map(fn ($entry) => [
                    'date' => $entry->observed_at?->toDateTimeString(), 'note' => $entry->outcome_note,
                    'goal' => $entry->goal?->title, 'activity' => $entry->activity?->title,
                    'animal' => $entry->animal?->name, 'evidence_tags' => $entry->evidence_tags,
                    'engagement' => $entry->engagement_rating, 'independence' => $entry->independence_rating,
                    'recorded_by' => $entry->recorder?->name,
                ]),
                'Portfolio items' => \App\Models\PortfolioItem::where('member_id', $member->id)->orderByDesc('achieved_on')->get()->map(fn ($item) => [
                    'type' => $item->type, 'title' => $item->title, 'description' => $item->description,
                    'achieved_on' => $item->achieved_on?->toDateString(), 'visible_to_member' => $item->visible_to_member,
                ]),
                'Certificates' => \App\Models\Certificate::where('member_id', $member->id)->orderByDesc('issued_on')->get()->map(fn ($certificate) => [
                    'number' => $certificate->certificate_number, 'title' => $certificate->title,
                    'description' => $certificate->description, 'issued_on' => $certificate->issued_on?->toDateString(),
                ]),
                'Invoices' => $member->invoices()->orderByDesc('invoice_date')->get()->map(fn ($invoice) => [
                    'reference' => $invoice->qb_reference, 'amount' => (float) $invoice->amount,
                    'invoice_date' => $invoice->invoice_date?->toDateString(), 'due_date' => $invoice->due_date?->toDateString(),
                    'paid_date' => $invoice->paid_date?->toDateString(), 'status' => $invoice->status,
                ]),
                'Finance profile' => collect([$member->financeProfile])->filter()->map(fn ($profile) => [
                    'attendance_type' => $profile->attendance_type, 'custom_day_rate' => $profile->custom_day_rate,
                    'one_to_one_hours_per_week' => $profile->one_to_one_hours_per_week,
                    'custom_one_to_one_rate' => $profile->custom_one_to_one_rate,
                    'charge_transport' => $profile->charge_transport, 'custom_transport_rate' => $profile->custom_transport_rate,
                    'notes' => $profile->notes,
                ])->values(),
                'Incidents' => $incidents->map(fn ($incident) => [
                    'date' => $incident->occurred_at?->toDateTimeString(), 'title' => $incident->title,
                    'location' => $incident->location, 'description' => $incident->description,
                    'persons_involved' => $incident->persons_involved, 'injury_details' => $incident->injury_details,
                    'severity' => $incident->severity, 'actions_taken' => $incident->actions_taken,
                    'status' => $incident->status, 'reported_by' => $incident->reportedBy?->name,
                ]),
                'Documents' => $documents->map(fn ($document) => [
                    'title' => $document->title, 'category' => $document->category,
                    'filename' => $document->original_name, 'uploaded_by' => $document->uploader?->name,
                    'uploaded_at' => $document->created_at?->toDateTimeString(),
                    'download_url' => route('documents.download', $document),
                ]),
                'Form submissions' => $formSubmissions->map(fn ($submission) => [
                    'form' => $submission->form?->title, 'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                    'submitted_by' => $submission->submittedBy?->name,
                    'answers' => $submission->data->mapWithKeys(fn ($answer) => [$answer->field?->label ?? "Field {$answer->field_id}" => $answer->value])->all(),
                ]),
            ],
        ]);
    }
}
