<?php

namespace App\Http\Controllers;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveReviewed;
use App\Notifications\LeaveSubmitted;
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
        ]);

        $leave = $request->user()->leaveRequests()->create([
            ...$data,
            'days' => LeaveRequest::weekdaysBetween(
                \Illuminate\Support\Carbon::parse($data['start_date']),
                \Illuminate\Support\Carbon::parse($data['end_date']),
            ),
            'status' => 'pending',
        ]);

        Notification::send(
            User::managers()->where('id', '!=', $request->user()->id)->get(),
            new LeaveSubmitted($leave),
        );

        return back()->with('success', 'Leave request submitted.');
    }

    public function review(Request $request, LeaveRequest $leave)
    {
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
