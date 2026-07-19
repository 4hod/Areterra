<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function index()
    {
        $today = today();

        $scheduled = Member::scheduledFor($today)->orderBy('first_name')->get();
        $attendance = Attendance::whereDate('date', $today)->get()->keyBy('member_id');

        // Anyone checked in today counts, even if not scheduled (ad-hoc attendance).
        $extras = Member::active()
            ->whereIn('id', $attendance->keys()->diff($scheduled->pluck('id')))
            ->get();

        $rows = $scheduled->map(fn ($m) => $this->row($m, $attendance->get($m->id), true))
            ->concat($extras->map(fn ($m) => $this->row($m, $attendance->get($m->id), false)));

        return Inertia::render('Register', [
            'date' => $today->toDateString(),
            'rows' => $rows->values(),
            'others' => Member::active()
                ->whereNotIn('id', $rows->pluck('id'))
                ->orderBy('first_name')
                ->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()]),
            'moods' => Attendance::MOODS,
        ]);
    }

    public function checkIn(Request $request, Member $member)
    {
        $data = $request->validate([
            'arrival_mood' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'notes' => ['nullable', 'string'],
        ]);

        // Carbon (not a Y-m-d string) so the lookup matches the cast storage format.
        Attendance::updateOrCreate(
            ['member_id' => $member->id, 'date' => today()],
            [...$data, 'checked_in' => true, 'checked_in_at' => now()],
        );

        return back()->with('success', "{$member->displayName()} checked in.");
    }

    public function update(Request $request, Member $member)
    {
        $data = $request->validate([
            'arrival_mood' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'notes' => ['nullable', 'string'],
            'checked_in' => ['sometimes', 'boolean'],
        ]);

        $attendance = Attendance::whereDate('date', today())
            ->where('member_id', $member->id)
            ->firstOrFail();

        if (($data['checked_in'] ?? true) === false) {
            $data['checked_in_at'] = null;
        }

        $attendance->update($data);

        return back()->with('success', 'Register updated.');
    }

    private function row(Member $member, ?Attendance $attendance, bool $scheduled): array
    {
        return [
            'id' => $member->id,
            'name' => $member->displayName(),
            'photo_path' => $member->photo_path,
            'scheduled' => $scheduled,
            'checked_in' => (bool) $attendance?->checked_in,
            'checked_in_at' => $attendance?->checked_in_at?->format('H:i'),
            'arrival_mood' => $attendance?->arrival_mood,
            'notes' => $attendance?->notes,
        ];
    }
}
