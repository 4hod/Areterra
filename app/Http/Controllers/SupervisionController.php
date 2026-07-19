<?php

namespace App\Http\Controllers;

use App\Models\StaffRosterMember;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupervisionController extends Controller
{
    public function index()
    {
        $subjects = $this->subjects();

        $supervisions = Supervision::with('supervisor:id,name')
            ->orderByDesc('date')
            ->get()
            ->groupBy(fn ($s) => $s->subject_type.':'.$s->subject_id);

        return Inertia::render('Supervisions', [
            'staff' => $subjects->map(function ($subject) use ($supervisions) {
                $history = $supervisions->get($subject['key'], collect());
                $latest = $history->first();

                return [
                    ...$subject,
                    'latest_date' => $latest?->date->toDateString(),
                    'overdue' => $latest === null || $latest->date->lt(now()->subWeeks(12)),
                    'history' => $history->map(fn ($s) => [
                        'id' => $s->id,
                        'type' => $s->type,
                        'date' => $s->date->toDateString(),
                        'duration_minutes' => $s->duration_minutes,
                        'supervisor' => $s->supervisor->name,
                        'discussion' => $s->discussion,
                        'actions_agreed' => $s->actions_agreed,
                        'development_notes' => $s->development_notes,
                        'next_due_date' => $s->next_due_date?->toDateString(),
                        'staff_signed_off' => $s->staff_signed_off,
                    ])->values(),
                ];
            })->values(),
            'types' => Supervision::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject_key' => ['required', 'string'], // "user:1" or "roster:2"
            'type' => ['required', 'in:'.implode(',', Supervision::TYPES)],
            'date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'discussion' => ['nullable', 'string'],
            'actions_agreed' => ['nullable', 'string'],
            'development_notes' => ['nullable', 'string'],
            'next_due_date' => ['nullable', 'date'],
            'staff_signed_off' => ['boolean'],
        ]);

        [$kind, $id] = explode(':', $data['subject_key']);
        $type = $kind === 'user' ? User::class : StaffRosterMember::class;
        abort_unless($type::whereKey($id)->exists(), 422);

        Supervision::create([
            ...collect($data)->except('subject_key')->all(),
            'subject_type' => $type,
            'subject_id' => (int) $id,
            'supervisor_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Supervision recorded.');
    }

    // Every supervisable person: system users plus active roster-only staff.
    private function subjects()
    {
        $users = User::orderBy('name')->get()->map(fn ($u) => [
            'key' => User::class.':'.$u->id,
            'subject_key' => "user:{$u->id}",
            'name' => $u->name,
            'job_title' => $u->job_title ?? ucfirst(str_replace('_', ' ', $u->role)),
        ]);

        $roster = StaffRosterMember::where('active', true)->orderBy('name')->get()->map(fn ($s) => [
            'key' => StaffRosterMember::class.':'.$s->id,
            'subject_key' => "roster:{$s->id}",
            'name' => $s->name,
            'job_title' => $s->job_title,
        ]);

        // A roster member with the same name as a user is likely the same person —
        // prefer the user account entry.
        $userNames = $users->pluck('name')->map(fn ($n) => mb_strtolower($n));

        return $users->concat($roster->reject(fn ($s) => $userNames->contains(mb_strtolower($s['name']))));
    }
}
