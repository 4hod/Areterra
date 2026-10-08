<?php

namespace App\Http\Controllers;

use App\Events\MemberAttended;
use App\Events\MemberMarkedAbsent;
use App\Events\MemberMarkedPresent;
use App\Models\Attendance;
use App\Models\DayCancellation;
use App\Workflows\CancelDay;
use App\Models\Member;
use App\Models\StaffAttendance;
use App\Models\StaffRosterMember;
use App\Models\User;
use App\Support\PrivateMedia;
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

        $staffAttendance = StaffAttendance::whereDate('date', $today)
            ->get()
            ->keyBy(fn (StaffAttendance $entry) => $entry->staff_type.':'.$entry->staff_id);

        $users = User::orderBy('name')->get();
        $userNames = $users->pluck('name')->map(fn ($name) => mb_strtolower($name));

        $staff = $users->map(fn (User $user) => $this->staffRow(
            'user',
            $user->id,
            $user->name,
            $user->job_title,
            $staffAttendance->get(User::class.':'.$user->id),
        ))->concat(
            StaffRosterMember::where('active', true)
                ->whereDoesntHave('user')
                ->orderBy('name')
                ->get()
                ->reject(fn (StaffRosterMember $person) => $userNames->contains(mb_strtolower($person->name)))
                ->map(fn (StaffRosterMember $person) => $this->staffRow(
                    'roster',
                    $person->id,
                    $person->name,
                    $person->job_title,
                    $staffAttendance->get(StaffRosterMember::class.':'.$person->id),
                ))
        )->sortBy('name')->values();

        return Inertia::render('Register', [
            'date' => $today->toDateString(),
            'rows' => $rows->values(),
            'others' => Member::active()
                ->whereNotIn('id', $rows->pluck('id'))
                ->orderBy('first_name')
                ->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()]),
            'moods' => Attendance::MOODS,
            'cancellation' => DayCancellation::whereDate('date', $today)->first(),
            'staff' => $staff,
        ]);
    }

    public function updateStaff(Request $request, string $kind, int $id)
    {
        $data = $request->validate([
            'present' => ['required', 'boolean'],
        ]);

        $person = match ($kind) {
            'user' => User::findOrFail($id),
            'roster' => StaffRosterMember::where('active', true)->findOrFail($id),
            default => abort(404),
        };

        StaffAttendance::updateOrCreate(
            [
                'date' => today(),
                'staff_type' => $person::class,
                'staff_id' => $person->getKey(),
            ],
            [
                'staff_name' => $person->name,
                'present' => $data['present'],
                'recorded_by' => $request->user()->id,
            ],
        );

        return back()->with('success', "{$person->name} marked ".($data['present'] ? 'here today.' : 'not on site.'));
    }

    public function checkIn(Request $request, Member $member)
    {
        $data = $request->validate([
            'arrival_mood' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'notes' => ['nullable', 'string'],
        ]);

        // Carbon (not a Y-m-d string) so the lookup matches the cast storage format.
        $attendance = Attendance::updateOrCreate(
            ['member_id' => $member->id, 'date' => today()],
            [
                ...$data,
                'checked_in' => true,
                'checked_in_at' => now(),
                'status' => 'present',
                'absence_reason' => null,
                'recorded_by' => $request->user()->id,
            ],
        );

        MemberMarkedPresent::dispatch($attendance);
        MemberAttended::dispatch($attendance);

        return back()->with('success', "{$member->displayName()} checked in.");
    }

    /**
     * Records an absence as a fact, so the rest of the Hub can react to it:
     * the afternoon transport run is cancelled, the day's transport charge is
     * reversed if they were never collected, and that day's session
     * participation is corrected.
     */
    /**
     * Marks a whole day off — no staff, weather, whatever. Cancels the sessions
     * and both transport legs, charges nobody, and records on each scheduled
     * member's file that the day was cancelled rather than that they were absent.
     */
    public function cancelDay(Request $request)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        (new CancelDay(
            \Carbon\CarbonImmutable::parse($data['date'] ?? today()),
            $data['reason'],
            $request->user()->id,
        ))->run();

        return back()->with('success', 'Day cancelled. Sessions, transport and charges all stood down.');
    }

    public function markAbsent(Request $request, Member $member)
    {
        $data = $request->validate([
            'absence_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance = Attendance::updateOrCreate(
            ['member_id' => $member->id, 'date' => today()],
            [
                'status' => 'absent',
                'checked_in' => false,
                'checked_in_at' => null,
                'absence_reason' => $data['absence_reason'] ?? null,
                'recorded_by' => $request->user()->id,
            ],
        );

        MemberMarkedAbsent::dispatch($attendance);

        return back()->with('success', "{$member->displayName()} marked absent. Afternoon transport cancelled.");
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
            $data['status'] = 'absent';
        } elseif (($data['checked_in'] ?? null) === true) {
            $data['status'] = 'present';
            $data['checked_in_at'] ??= now();
        }

        $attendance->update($data);

        return back()->with('success', 'Register updated.');
    }

    private function row(Member $member, ?Attendance $attendance, bool $scheduled): array
    {
        return [
            'id' => $member->id,
            'name' => $member->displayName(),
            'photo_path' => PrivateMedia::memberPhotoUrl($member),
            'scheduled' => $scheduled,
            'status' => $attendance?->status ?? 'expected',
            'absence_reason' => $attendance?->absence_reason,
            'checked_in' => (bool) $attendance?->checked_in,
            'checked_in_at' => $attendance?->checked_in_at?->format('H:i'),
            'arrival_mood' => $attendance?->arrival_mood,
            'notes' => $attendance?->notes,
        ];
    }

    private function staffRow(string $kind, int $id, string $name, ?string $jobTitle, ?StaffAttendance $attendance): array
    {
        return [
            'key' => $kind.':'.$id,
            'kind' => $kind,
            'id' => $id,
            'name' => $name,
            'job_title' => $jobTitle,
            'present' => (bool) $attendance?->present,
        ];
    }
}
