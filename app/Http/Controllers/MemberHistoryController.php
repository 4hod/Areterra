<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberHistoryController extends Controller
{
    public function show(Request $request, Member $member)
    {
        $eod = EndOfDayRecord::where('member_id', $member->id)
            ->orderByDesc('date')
            ->paginate(15, pageName: 'eod_page')
            ->withQueryString();

        $attendance = Attendance::where('member_id', $member->id)
            ->orderByDesc('date')
            ->paginate(20, pageName: 'attendance_page')
            ->withQueryString();

        $wordpressNotes = $member->notes()
            ->where('source', 'wordpress')
            ->orderByDesc('noted_at')
            ->orderByDesc('id')
            ->paginate(30, pageName: 'wordpress_page')
            ->withQueryString();

        return Inertia::render('Members/History', [
            'member' => ['id' => $member->id, 'name' => $member->displayName()],
            'endOfDay' => $eod->through(fn ($r) => [
                'id' => $r->id,
                'date' => $r->date->toDateString(),
                'arrival_mood' => $r->arrival_mood,
                'end_mood' => $r->end_mood,
                'session_type' => $r->session_type,
                'activities' => $r->activities,
                'food_intake' => $r->food_intake,
                'fluid_intake' => $r->fluid_intake,
                'toileting_notes' => $r->toileting_notes,
                'medication_given' => $r->medication_given,
                'medication_notes' => $r->medication_notes,
                'incident' => $r->incident,
                'incident_detail' => $r->incident_detail,
                'photos' => $r->photos ?? [],
                'notes' => $r->notes,
                'concern' => $r->concern,
                'concern_detail' => $r->concern_detail,
            ]),
            'attendance' => $attendance->through(fn ($a) => [
                'id' => $a->id,
                'date' => $a->date->toDateString(),
                'checked_in' => $a->checked_in,
                'checked_in_at' => $a->checked_in_at?->toTimeString(),
                'arrival_mood' => $a->arrival_mood,
                'notes' => $a->notes,
            ]),
            'wordpressNotes' => $wordpressNotes->through(fn ($note) => [
                'id' => $note->id,
                'note_type' => $note->note_type,
                'note' => $note->note,
                'author_name' => $note->author_name,
                'noted_at' => $note->noted_at?->toIso8601String(),
            ]),
        ]);
    }
}
