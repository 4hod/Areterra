<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveReviewed;
use App\Notifications\LeaveSubmitted;
use App\Support\LeaveCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isManager = Gate::allows('view_all_leave');

        return Inertia::render('Leave', [
            'balance' => LeaveBalance::remainingFor($user),
            'myRequests' => $user->leaveRequests()->orderByDesc('start_date')->limit(20)->get(),
            'types' => LeaveRequest::TYPES,
            'isManager' => $isManager,
            'pending' => $isManager
                ? LeaveRequest::with('user:id,name')->where('status', 'pending')->orderBy('start_date')->get()
                : [],
            'upcoming' => $isManager
                ? LeaveRequest::with('user:id,name')
                    ->where('status', 'approved')
                    ->where('end_date', '>=', today())
                    ->orderBy('start_date')
                    ->limit(30)
                    ->get()
                : [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', LeaveRequest::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
            'start_half_day' => ['sometimes', 'boolean'],
            'end_half_day' => ['sometimes', 'boolean'],
        ]);

        $start = \Illuminate\Support\Carbon::parse($data['start_date']);
        $end = \Illuminate\Support\Carbon::parse($data['end_date']);
        $daysByYear = LeaveCalendar::daysByYear(
            $request->user(),
            $start,
            $end,
            (bool) ($data['start_half_day'] ?? false),
            (bool) ($data['end_half_day'] ?? false),
        );

        $leave = $request->user()->leaveRequests()->create([
            ...$data,
            'days' => array_sum($daysByYear),
            'days_by_year' => $daysByYear,
            'status' => 'pending',
        ]);

        Notification::send(
            User::whereHas('capabilityGrants', fn ($query) => $query->where('capability', 'approve_leave'))
                ->where('id', '!=', $request->user()->id)->get(),
            new LeaveSubmitted($leave),
        );

        return back()->with('success', 'Leave request submitted.');
    }

    public function review(Request $request, LeaveRequest $leave)
    {
        abort_if($leave->user_id === $request->user()->id, 403, 'You cannot review your own leave request.');
        abort_unless($leave->status === 'pending', 422, 'This leave request has already been reviewed.');

        $data = $request->validate([
            'status' => ['required', 'in:approved,declined'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $leave->update([
            ...$data,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $leave->user->notify(new LeaveReviewed($leave));

        return back()->with('success', 'Request '.$data['status'].'.');
    }
}
