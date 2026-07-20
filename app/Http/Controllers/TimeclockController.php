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
            'openEntry' => ($open = $user->timeclockEntries()->whereNull('clock_out')->latest('clock_in')->first())
                ? $entryRow($open->setRelation('user', $user))
                : null,
            'myEntries' => $user->timeclockEntries()->with('user:id,name')
                ->orderByDesc('clock_in')->limit(15)->get()->map($entryRow),
            'isManager' => Gate::allows('view_all_timeclock'),
            'from' => ($from = $request->date('from') ?? now()->startOfWeek())->toDateString(),
            'to' => ($to = $request->date('to') ?? today())->toDateString(),
            'weekEntries' => Gate::allows('view_all_timeclock')
                ? TimeclockEntry::with('user:id,name')
                    ->whereBetween('clock_in', [$from, $to->copy()->endOfDay()])
                    ->orderByDesc('clock_in')
                    ->get()
                    ->map($entryRow)
                : [],
        ]);
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
