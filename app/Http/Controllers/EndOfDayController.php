<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Support\PrivateMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'rows' => $attendees->map(function ($a) use ($records) {
                $record = $records->get($a->member_id);

                return [
                    'id' => $a->member->id,
                    'name' => $a->member->displayName(),
                    'arrival_mood' => $a->arrival_mood,
                    'record' => $record ? [
                        ...$record->only([
                            'end_mood', 'session_type', 'activities',
                            'food_intake', 'fluid_intake', 'toileting_notes',
                            'medication_given', 'medication_notes',
                            'incident', 'incident_detail',
                            'notes', 'concern', 'concern_detail',
                        ]),
                        'photos' => PrivateMedia::endOfDayPhotoUrls($a->member, $record),
                    ] : null,
                ];
            })->values(),
            'moods' => Attendance::MOODS,
        ]);
    }

    public function store(Request $request, Member $member)
    {
        $data = $request->validate([
            'end_mood' => ['nullable', 'in:'.implode(',', Attendance::MOODS)],
            'session_type' => ['nullable', 'string', 'max:100'],
            'activities' => ['nullable', 'string'],
            'food_intake' => ['nullable', 'in:'.implode(',', EndOfDayRecord::INTAKE_LEVELS)],
            'fluid_intake' => ['nullable', 'in:'.implode(',', EndOfDayRecord::INTAKE_LEVELS)],
            'toileting_notes' => ['nullable', 'string'],
            'medication_given' => ['boolean'],
            'medication_notes' => ['nullable', 'string'],
            'incident' => ['boolean'],
            'incident_detail' => ['nullable', 'string', 'required_if:incident,true'],
            'notes' => ['nullable', 'string'],
            'concern' => ['boolean'],
            'concern_detail' => ['nullable', 'string', 'required_if:concern,true'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['image', 'max:8192'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['string'],
        ]);

        $arrival = Attendance::whereDate('date', today())
            ->where('member_id', $member->id)
            ->value('arrival_mood');

        $existing = EndOfDayRecord::where('member_id', $member->id)->whereDate('date', today())->first();
        $existingPhotos = collect($existing?->photos ?? []);
        $existingUrls = $existing ? PrivateMedia::endOfDayPhotoUrls($member, $existing) : [];
        $removeUrls = $data['remove_photos'] ?? [];
        $removedPaths = $existingPhotos
            ->filter(fn ($path, $index) => in_array($existingUrls[$index] ?? '', $removeUrls, true));
        $photos = $existingPhotos
            ->reject(fn ($path, $index) => in_array($existingUrls[$index] ?? '', $removeUrls, true))
            ->map(fn ($path) => PrivateMedia::path($path))
            ->filter()
            ->values();

        foreach ($request->file('photos', []) as $file) {
            $photos->push($file->store('end-of-day-photos', 'local'));
        }

        $record = EndOfDayRecord::updateOrCreate(
            ['member_id' => $member->id, 'date' => today()],
            [
                ...collect($data)->except(['photos', 'remove_photos'])->all(),
                'photos' => $photos->all(),
                'arrival_mood' => $arrival,
                'user_id' => $request->user()->id,
            ],
        );

        foreach ($removedPaths as $removedPath) {
            if ($path = PrivateMedia::path($removedPath)) {
                Storage::disk('local')->delete($path);
                Storage::disk('public')->delete($path);
            }
        }

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
