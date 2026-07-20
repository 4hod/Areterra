<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EndOfDayController extends Controller
{
    public function index()
    {
        $today = today();

        $attendees = Attendance::with('member')
            ->whereDate('date', $today)
            ->where('checked_in', true)
            ->get();

        $records = EndOfDayRecord::whereDate('date', $today)->get()->keyBy('member_id');

        return Inertia::render('EndOfDay', [
            'date' => $today->toDateString(),
            'rows' => $attendees->map(fn ($a) => [
                'id' => $a->member->id,
                'name' => $a->member->displayName(),
                'arrival_mood' => $a->arrival_mood,
                'record' => $records->get($a->member_id)?->only([
                    'end_mood', 'session_type', 'activities', 'notes', 'concern', 'concern_detail',
                ]),
            ])->values(),
            'moods' => Attendance::MOODS,
        ]);
    }

    public function store(Request $request, Member $member)
    {
        $data = $request->validate([
            'end_mood' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'session_type' => ['nullable', 'string', 'max:100'],
            'activities' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'concern' => ['boolean'],
            'concern_detail' => ['nullable', 'string', 'required_if:concern,true'],
        ]);

        $arrival = Attendance::whereDate('date', today())
            ->where('member_id', $member->id)
            ->value('arrival_mood');

        $record = EndOfDayRecord::updateOrCreate(
            ['member_id' => $member->id, 'date' => today()],
            [...$data, 'arrival_mood' => $arrival, 'user_id' => $request->user()->id],
        );

        if ($record->concern && ($record->wasRecentlyCreated || $record->wasChanged('concern'))) {
            // Concerns flagged at end of day auto-create a safeguarding entry (SPEC.md §25).
            \App\Models\SafeguardingConcern::firstOrCreate(
                ['end_of_day_record_id' => $record->id],
                [
                    'member_id' => $member->id,
                    'reported_by' => $request->user()->id,
                    'source' => 'end_of_day',
                    'date' => today(),
                    'details' => $record->concern_detail ?? 'Concern flagged in end-of-day record.',
                ],
            );

            \Illuminate\Support\Facades\Notification::send(
                \App\Models\User::managers()->get(),
                new \App\Notifications\ConcernRaised(
                    "End of day concern: {$member->displayName()}",
                    $record->concern_detail ?? 'A concern was flagged in today\'s end-of-day record.',
                    '/end-of-day',
                    'safeguarding',
                ),
            );
        }

        return back()->with('success', "End of day saved for {$member->displayName()}.");
    }
}
