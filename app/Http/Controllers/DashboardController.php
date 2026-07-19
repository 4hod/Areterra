<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\TodayChecklist;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(TodayChecklist $checklist)
    {
        $today = today();

        $activeAnimals = Animal::active()->count();
        $checkedAnimals = Animal::active()
            ->whereHas('welfareChecks', fn ($q) => $q->whereDate('created_at', $today))
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'membersInToday' => Attendance::whereDate('date', $today)->where('checked_in', true)->count(),
                'membersScheduled' => Member::scheduledFor($today)->count(),
                'animalsNeedingChecks' => max(0, $activeAnimals - $checkedAnimals),
            ],
            'welfareAlerts' => Animal::active()
                ->whereIn('welfare_status', ['amber', 'red'])
                ->get(['id', 'name', 'species', 'welfare_status']),
            'checklist' => $checklist->build($today),
        ]);
    }
}
