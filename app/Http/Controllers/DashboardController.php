<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\ImpactEntry;
use App\Models\Incident;
use App\Models\LeaveBalance;
use App\Models\Member;
use App\Models\Setting;
use App\Models\StaffAttendance;
use App\Services\TodayChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TodayChecklist $checklist)
    {
        $today = today();
        $user = $request->user();

        $activeAnimals = Animal::active()->count();
        $checkedAnimals = Animal::active()
            ->whereHas('welfareChecks', fn ($q) => $q
                ->whereDate('created_at', $today)
                ->where('fed', true)
                ->where('pellets_given', true)
                ->where('greenery_given', true)
                ->where('fresh_water', true)
                ->where('hay_topped_up', true))
            ->count();

        // 7-day attendance trend for the dashboard sparkline.
        $attendanceTrend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = today()->subDays($daysAgo);

            return Attendance::whereDate('date', $date)->where('checked_in', true)->count();
        })->values();

        $thisWeekAttendance = Attendance::where('checked_in', true)->whereBetween('date', [$today->copy()->subDays(6), $today])->count();
        $previousWeekAttendance = Attendance::where('checked_in', true)->whereBetween('date', [$today->copy()->subDays(13), $today->copy()->subDays(7)])->count();

        return Inertia::render('Dashboard', [
            // Derived from the records themselves — nothing here is a stored
            // reminder. Cached for an hour so it costs nothing on page load.
            'needsAttention' => \App\Support\DueScanner::scanThrottled(14, $user),
            'orgIsEmpty' => Member::count() === 0 && Animal::count() === 0,
            'birthdays' => Member::active()->whereNotNull('dob')->get()
                ->filter(function ($m) {
                    // Compare month+day only, across a 7-day window that can wrap year-end.
                    $next = today()->setMonth($m->dob->month)->setDay($m->dob->day);
                    if ($next->lt(today())) {
                        $next = $next->addYear();
                    }
                    return $next->diffInDays(today()) <= 7;
                })
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->displayName(),
                    'date' => $m->dob->format('j M'),
                    'is_today' => $m->dob->format('m-d') === today()->format('m-d'),
                ])
                ->values(),
            'stats' => [
                'memberRecords' => Member::active()->count(),
                'membersInToday' => Attendance::whereDate('date', $today)->where('checked_in', true)->count(),
                'membersScheduled' => Member::scheduledFor($today)->count(),
                'animalsChecked' => $checkedAnimals,
                'animalsTotal' => $activeAnimals,
                'animalsNeedingChecks' => max(0, $activeAnimals - $checkedAnimals),
                'attendanceTrend' => $attendanceTrend,
            ],
            'welfareAlerts' => Animal::active()
                ->whereIn('welfare_status', ['amber', 'red'])
                ->get(['id', 'name', 'species', 'welfare_status']),
            'checklist' => $checklist->build($today),
            'banner' => Setting::get('banner_text'),
            'staffAvatars' => StaffAttendance::whereDate('date', $today)
                ->where('present', true)
                ->orderBy('staff_name')
                ->limit(12)
                ->pluck('staff_name'),
            'leaveBalance' => LeaveBalance::remainingFor($user),
            'announcements' => Announcement::with('author:id,name')->latest()->limit(3)->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'author' => $a->author->name,
                    'created_at' => $a->created_at->toDateTimeString(),
                    'read' => $a->readBy($user),
                ]),
            'notifications' => $user->notifications()->latest()->limit(8)->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? '/',
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
            'servicePulse' => Gate::allows('view_reports') ? [
                'attendanceThisWeek' => $thisWeekAttendance,
                'attendanceDirection' => $thisWeekAttendance <=> $previousWeekAttendance,
                'progressEvidence' => ImpactEntry::where('observed_at', '>=', $today->copy()->subDays(30))->count(),
                'openIncidents' => Incident::where('status', '!=', 'closed')->count(),
                'dailyStoriesToday' => EndOfDayRecord::whereDate('date', $today)->count(),
            ] : null,
        ]);
    }
}
