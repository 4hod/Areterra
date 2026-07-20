<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->date('month') ?? today();

        return Inertia::render('Activities', [
            'month' => $month->format('Y-m'),
            'activities' => Activity::with('user:id,name')
                ->whereBetween('activity_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->orderBy('activity_date')
                ->orderBy('start_time')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'activity_date' => $a->activity_date->toDateString(),
                    'start_time' => $a->start_time ? substr($a->start_time, 0, 5) : null,
                    'description' => $a->description,
                    'user' => $a->user->name,
                ]),
            'upcoming' => Activity::where('activity_date', '>=', today())
                ->orderBy('activity_date')
                ->limit(5)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'activity_date' => $a->activity_date->toDateString(),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        Activity::create([
            ...$request->validate([
                'title' => ['required', 'string', 'max:200'],
                'activity_date' => ['required', 'date'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'description' => ['nullable', 'string'],
            ]),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Activity added.');
    }
}
