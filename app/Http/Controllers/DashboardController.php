<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\LeaveBalance;
use App\Models\Member;
use App\Models\Setting;
use App\Models\User;
use App\Services\TodayChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TodayChecklist $checklist)
    {
        $today = today();
        $user = $request->user();

        $activeAnimals = Animal::active()->count();
        $checkedAnimals = Animal::active()
            ->whereHas('welfareChecks', fn ($q) => $q->whereDate('created_at', $today))
            ->count();

        $openShift = $user->timeclockEntries()->whereNull('clock_out')->latest('clock_in')->first();

        // 7-day attendance trend for the dashboard sparkline.
        $attendanceTrend = collect(range(6, 0))->map(function ($daysAgo) {
            $date = today()->subDays($daysAgo);

            return Attendance::whereDate('date', $date)->where('checked_in', true)->count();
        })->values();

        return Inertia::render('Dashboard', [
            'orgIsEmpty' => Member::count() === 0 && Animal::count() === 0,
            'stats' => [
                'membersInToday' => Attendance::whereDate('date', $today)->where('checked_in', true)->count(),
                'membersScheduled' => Member::scheduledFor($today)->count(),
                'animalsNeedingChecks' => max(0, $activeAnimals - $checkedAnimals),
                'attendanceTrend' => $attendanceTrend,
            ],
            'welfareAlerts' => Animal::active()
                ->whereIn('welfare_status', ['amber', 'red'])
                ->get(['id', 'name', 'species', 'welfare_status']),
            'checklist' => $checklist->build($today),
            'banner' => Setting::get('banner_text'),
            'staffAvatars' => User::orderBy('name')->limit(12)->pluck('name'),
            'myShift' => $openShift ? ['clock_in' => $openShift->clock_in->format('H:i')] : null,
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
        ]);
    }
}
