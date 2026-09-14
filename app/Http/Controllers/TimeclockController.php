<?php

namespace App\Http\Controllers;

use App\Models\TimeclockEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TimeclockController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $from = $request->date('from') ?? now()->startOfWeek();
        $to = $request->date('to') ?? today();
        $contractedHours = $user->contracted_hours
            ?? $user->rates()->orderByDesc('effective_from')->value('contracted_hours');

        $entryRow = fn (TimeclockEntry $e) => [
            'id' => $e->id,
            'user' => $e->user->name,
            'clock_in' => $e->clock_in->toDateTimeString(),
            'clock_out' => $e->clock_out?->toDateTimeString(),
            'break_minutes' => $e->break_minutes,
            'worked_minutes' => $e->workedMinutes(),
            'notes' => $e->notes,
        ];

        return Inertia::render('Timeclock', [
            'contractedHours' => $contractedHours === null ? null : (float) $contractedHours,
            'additionalEntries' => $user->additionalHoursEntries()->orderByDesc('work_date')->limit(20)->get()->map(fn ($e) => [
                'id' => $e->id,
                'user' => $user->name,
                'work_date' => $e->work_date->toDateString(),
                'minutes' => $e->minutes,
                'notes' => $e->notes,
            ]),
            'staffContracts' => Gate::allows('edit_timeclock')
                ? User::orderBy('name')->get(['id', 'name', 'contracted_hours'])->map(fn ($staff) => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'contracted_hours' => $staff->contracted_hours
                        ?? $staff->rates()->orderByDesc('effective_from')->value('contracted_hours'),
                ])
                : [],
            'allAdditionalEntries' => Gate::allows('view_all_timeclock')
                ? \App\Models\AdditionalHoursEntry::with('user:id,name')->whereBetween('work_date', [$from, $to])->orderByDesc('work_date')->get()->map(fn ($e) => [
                    'id' => $e->id, 'user' => $e->user->name, 'work_date' => $e->work_date->toDateString(), 'minutes' => $e->minutes, 'notes' => $e->notes,
                ]) : [],
            'openEntry' => ($open = $user->timeclockEntries()->whereNull('clock_out')->latest('clock_in')->first())
                ? $entryRow($open->setRelation('user', $user))
                : null,
            'myEntries' => $user->timeclockEntries()->with('user:id,name')
                ->orderByDesc('clock_in')->limit(15)->get()->map($entryRow),
            'isManager' => Gate::allows('view_all_timeclock'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'weekEntries' => Gate::allows('view_all_timeclock')
                ? TimeclockEntry::with('user:id,name')
                    ->whereBetween('clock_in', [$from, $to->copy()->endOfDay()])
                    ->orderByDesc('clock_in')
                    ->get()
                    ->map($entryRow)
                : [],
        ]);
    }

    public function storeAdditional(Request $request)
    {
        $data = $request->validate([
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        $request->user()->additionalHoursEntries()->create([
            'work_date' => $data['work_date'],
            'minutes' => (int) round($data['hours'] * 60),
            'notes' => $data['notes'],
        ]);

        return back()->with('success', 'Additional hours logged.');
    }

    public function updateContract(Request $request, User $user)
    {
        Gate::authorize('edit_timeclock');
        $data = $request->validate(['contracted_hours' => ['nullable', 'numeric', 'min:0', 'max:168']]);
        $user->update($data);

        return back()->with('success', 'Contracted hours updated.');
    }

    public function clockIn(Request $request)
    {
        $user = $request->user();

        if ($user->timeclockEntries()->whereNull('clock_out')->exists()) {
            return back()->with('error', 'You are already clocked in.');
        }

        $user->timeclockEntries()->create(['clock_in' => now()]);

        return back()->with('success', 'Clocked in at '.now()->format('H:i').'.');
    }

    public function clockOut(Request $request)
    {
        $data = $request->validate([
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'notes' => ['nullable', 'string'],
        ]);

        $open = $request->user()->timeclockEntries()->whereNull('clock_out')->latest('clock_in')->first();

        if (! $open) {
            return back()->with('error', 'You are not clocked in.');
        }

        $open->update([
            'clock_out' => now(),
            'break_minutes' => $data['break_minutes'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Clocked out at '.now()->format('H:i').'.');
    }

    public function update(Request $request, TimeclockEntry $entry)
    {
        Gate::authorize('edit_timeclock');

        $data = $request->validate([
            'clock_in' => ['required', 'date'],
            'clock_out' => ['nullable', 'date', 'after:clock_in'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry->update([...$data, 'edited_by' => $request->user()->id]);

        return back()->with('success', 'Entry updated.');
    }
}
