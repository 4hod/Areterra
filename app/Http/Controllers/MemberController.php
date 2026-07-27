<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $members = Member::query()
            ->with('settings')
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $s = $request->string('search');
                $q->where(fn ($w) => $w
                    ->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('preferred_name', 'like', "%{$s}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('first_name')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->displayName(),
                'status' => $m->status,
                'photo_path' => $m->photo_path,
                'attendance_days' => $m->settings?->attendance_days ?? [],
            ]);

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Member $member)
    {
        $member->load('settings.keyWorker');
        $detailed = Gate::allows('view_member_details');

        return Inertia::render('Members/Show', [
            'member' => [
                'id' => $member->id,
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'preferred_name' => $member->preferred_name,
                'name' => $member->displayName(),
                'status' => $member->status,
                'dob' => $detailed ? $member->dob?->toDateString() : null,
                'nhs_number' => $detailed ? $member->nhs_number : null,
                'support_needs' => $detailed ? $member->support_needs : null,
                'medical_notes' => $detailed ? $member->medical_notes : null,
                'interests' => $detailed ? $member->interests : null,
                'diagnoses' => $detailed ? $member->diagnoses : null,
                'emergency_contacts' => $detailed ? ($member->emergency_contacts ?? []) : [],
                'phone' => $member->phone,
                'email' => $member->email,
                'address_line1' => $member->address_line1,
                'address_line2' => $member->address_line2,
                'town' => $member->town,
                'postcode' => $member->postcode,
                'photo_path' => $member->photo_path,
                'gp_name' => $detailed ? $member->gp_name : null,
                'gp_practice' => $detailed ? $member->gp_practice : null,
                'gp_phone' => $detailed ? $member->gp_phone : null,
                'medication' => $detailed ? $member->medication : null,
                'settings' => [
                    'transport_required' => (bool) $member->settings?->transport_required,
                    'attendance_days' => $member->settings?->attendance_days ?? [],
                    'key_worker' => $member->settings?->keyWorker?->name,
                    'key_worker_id' => $member->settings?->key_worker_id,
                ],
            ],
            'memberNotes' => $detailed
                ? $member->notes()->orderByDesc('noted_at')->orderByDesc('id')->get()
                    ->map(fn ($note) => [
                        'id' => $note->id,
                        'note_type' => $note->note_type,
                        'note' => $note->note,
                        'author_name' => $note->author_name,
                        'noted_at' => $note->noted_at?->toIso8601String(),
                        'source' => $note->source,
                    ])
                : [],
            'recentAttendance' => $member->attendances()->orderByDesc('date')->limit(10)
                ->get(['id', 'date', 'checked_in', 'arrival_mood', 'notes']),
            'recentEndOfDay' => $member->endOfDayRecords()->with('user:id,name')->orderByDesc('date')->limit(10)
                ->get()
                ->map(fn ($record) => [
                    'id' => $record->id,
                    'date' => $record->date->toDateString(),
                    'arrival_mood' => $record->arrival_mood,
                    'end_mood' => $record->end_mood,
                    'session_type' => $record->session_type,
                    'activities' => $record->activities,
                    'notes' => $record->notes,
                    'concern' => $record->concern,
                    'incident' => $record->incident,
                    'author' => $record->source_author_name ?: $record->user?->name,
                ]),
            'abcObservations' => $detailed
                ? $member->abcObservations()->with('user:id,name')->orderByDesc('observed_at')->limit(20)->get()
                    ->map(fn ($o) => [
                        'id' => $o->id,
                        'observed_at' => $o->observed_at->toDateTimeString(),
                        'antecedent' => $o->antecedent,
                        'behaviour' => $o->behaviour,
                        'consequence' => $o->consequence,
                        'wellbeing_score' => $o->wellbeing_score,
                        'concern' => $o->concern,
                        'user' => $o->user->name,
                    ])
                : [],
            'bodyMaps' => $detailed
                ? $member->bodyMaps()->with('user:id,name')->orderByDesc('recorded_at')->limit(10)->get()
                    ->map(fn ($b) => [
                        'id' => $b->id,
                        'recorded_at' => $b->recorded_at->toDateTimeString(),
                        'markers' => $b->markers,
                        'notes' => $b->notes,
                        'user' => $b->user->name,
                    ])
                : [],
            'commsLog' => $detailed
                ? $member->commsLog()->with('user:id,name')->orderByDesc('date')->limit(30)->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'type' => $c->type,
                        'direction' => $c->direction,
                        'subject' => $c->subject,
                        'summary' => $c->summary,
                        'contact_name' => $c->contact_name,
                        'organisation' => $c->organisation,
                        'date' => $c->date->toDateString(),
                        'user' => $c->user->name,
                    ])
                : [],
            'contacts' => $detailed
                ? $member->contacts()->get(['id', 'name', 'role', 'organisation', 'email', 'phone', 'notes'])
                : [],
            'goals' => $member->goals()->with('outcomes')->orderByDesc('created_at')->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'title' => $g->title,
                    'description' => $g->description,
                    'status' => $g->status,
                    'target_date' => $g->target_date?->toDateString(),
                    'achieved_at' => $g->achieved_at?->toDateString(),
                ]),
            'outcomes' => $member->outcomes()->with(['goal:id,title', 'user:id,name'])->orderByDesc('date')->limit(20)->get()
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'date' => $o->date->toDateString(),
                    'outcome' => $o->outcome,
                    'goal' => $o->goal?->title,
                    'user' => $o->user->name,
                ]),
            'alerts' => $detailed
                ? $member->alerts()->get()->map(fn ($a) => [
                    'id' => $a->id, 'type' => $a->type, 'text' => $a->text, 'severity' => $a->severity,
                ])
                : [],
            'consents' => $detailed
                ? $member->consents()->get()->map(fn ($c) => [
                    'consent_type' => $c->consent_type,
                    'granted' => $c->granted,
                    'recorded_on' => $c->recorded_on->toDateString(),
                    'notes' => $c->notes,
                ])
                : [],
            'canEdit' => Gate::allows('edit_members'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $member = Member::create($data['member']);
        $member->settings()->create($data['settings']);

        return redirect()->route('members.show', $member)->with('success', 'Member created.');
    }

    public function update(Request $request, Member $member)
    {
        $data = $this->validated($request);

        $member->update($data['member']);
        $member->settings()->updateOrCreate([], $data['settings']);

        return back()->with('success', 'Member updated.');
    }

    public function bulkUpdateStatus(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['exists:members,id'],
            'status' => ['required', 'in:active,inactive,on-leave,archived'],
        ]);

        $count = Member::whereIn('id', $data['ids'])->update(['status' => $data['status']]);

        return back()->with('success', "{$count} member".($count === 1 ? '' : 's')." set to {$data['status']}.");
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive,on-leave,archived'],
            'dob' => ['nullable', 'date'],
            'nhs_number' => ['nullable', 'string', 'max:20'],
            'support_needs' => ['nullable', 'string'],
            'diagnoses' => ['nullable', 'string'],
            'emergency_contacts' => ['nullable', 'array'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:100'],
            'emergency_contacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:10'],
            'gp_name' => ['nullable', 'string', 'max:100'],
            'gp_practice' => ['nullable', 'string', 'max:200'],
            'gp_phone' => ['nullable', 'string', 'max:30'],
            'medication' => ['nullable', 'string'],
            'transport_required' => ['boolean'],
            'attendance_days' => ['nullable', 'array'],
            'attendance_days.*' => ['integer', 'between:1,7'],
            'key_worker_id' => ['nullable', 'exists:users,id'],
        ]);

        return [
            'member' => collect($validated)->except(['transport_required', 'attendance_days', 'key_worker_id'])->all(),
            'settings' => [
                'transport_required' => $validated['transport_required'] ?? false,
                'attendance_days' => $validated['attendance_days'] ?? [],
                'key_worker_id' => $validated['key_worker_id'] ?? null,
            ],
        ];
    }
}
