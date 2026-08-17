<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WeeklyPlannerController extends Controller
{
    private function weekStart(Request $request): \Carbon\Carbon
    {
        $anchor = $request->date('week') ?? today();

        return $anchor->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    }

    public function index(Request $request)
    {
        $start = $this->weekStart($request);
        $end = $start->copy()->addDays(6);

        return Inertia::render('WeeklyPlanner', [
            'weekStart' => $start->toDateString(),
            'weekEnd' => $end->toDateString(),
            'prevWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
            'days' => $this->daysWithActivities($start, $end),
        ]);
    }

    public function print(Request $request)
    {
        $start = $this->weekStart($request);
        $end = $start->copy()->addDays(6);

        return Inertia::render('WeeklyPlannerPrint', [
            'weekStart' => $start->toDateString(),
            'weekEnd' => $end->toDateString(),
            'days' => $this->daysWithActivities($start, $end),
        ]);
    }

    private function daysWithActivities(\Carbon\Carbon $start, \Carbon\Carbon $end): array
    {
        $activities = Activity::with('user:id,name')
            ->whereBetween('activity_date', [$start, $end])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn ($a) => $a->activity_date->toDateString());

        $days = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->toDateString();
            $days[] = [
                'date' => $dateStr,
                'label' => $d->format('l'),
                'short' => $d->format('D j M'),
                'activities' => ($activities->get($dateStr) ?? collect())->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'start_time' => $a->start_time ? substr($a->start_time, 0, 5) : null,
                    'description' => $a->description,
                    'user' => $a->user->name,
                ])->values(),
            ];
        }

        return $days;
    }
}
