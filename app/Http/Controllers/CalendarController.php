<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->filled('month')
            ? \Carbon\Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $activities = Activity::whereBetween('activity_date', [$start, $end])->get()
            ->map(fn ($a) => [
                'date' => $a->activity_date->toDateString(),
                'type' => 'activity',
                'title' => $a->title,
                'id' => $a->id,
            ]);

        $canManageLeave = Gate::allows('approve_leave');

        $leaveQuery = LeaveRequest::with('user:id,name')
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);

        if (! $canManageLeave) {
            $leaveQuery->where('user_id', $request->user()->id);
        }

        $leave = $leaveQuery->get()->flatMap(function ($l) use ($start, $end) {
            $rows = [];
            $cursor = $l->start_date->max($start);
            $last = $l->end_date->min($end);
            while ($cursor->lte($last)) {
                $rows[] = [
                    'date' => $cursor->toDateString(),
                    'type' => 'leave',
                    'title' => "{$l->user->name} — {$l->type}",
                    'id' => $l->id,
                    'status' => $l->status,
                ];
                $cursor = $cursor->copy()->addDay();
            }

            return $rows;
        });

        return Inertia::render('Calendar', [
            'month' => $month->format('Y-m'),
            'prevMonth' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'events' => $activities->concat($leave)->values(),
            'canManageLeave' => $canManageLeave,
            'pendingLeave' => $canManageLeave
                ? LeaveRequest::with('user:id,name')->where('status', 'pending')->get()
                    ->map(fn ($l) => [
                        'id' => $l->id,
                        'user' => $l->user->name,
                        'leave_type' => $l->type,
                        'start_date' => $l->start_date->toDateString(),
                        'end_date' => $l->end_date->toDateString(),
                        'days' => (float) $l->days,
                        'reason' => $l->reason,
                    ])
                : [],
        ]);
    }
}
