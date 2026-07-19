<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveReviewed;
use App\Notifications\LeaveSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_leave_days_count_weekdays_only(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);

        // Mon 6 July 2026 – Sun 12 July 2026 = 5 weekdays.
        $this->actingAs($staff)->post('/leave', [
            'type' => 'annual',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
        ])->assertRedirect();

        $this->assertSame(5.0, (float) LeaveRequest::first()->days);
        Notification::assertSentTo($manager, LeaveSubmitted::class);
    }

    public function test_manager_approval_updates_balance_and_notifies_staff(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);

        $leave = $staff->leaveRequests()->create([
            'type' => 'annual',
            'start_date' => now()->startOfYear()->addMonths(2)->next('Monday'),
            'end_date' => now()->startOfYear()->addMonths(2)->next('Monday')->addDays(4),
            'days' => 5,
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->put("/leave/{$leave->id}/review", ['status' => 'approved'])
            ->assertRedirect();

        Notification::assertSentTo($staff, LeaveReviewed::class);

        $balance = LeaveBalance::remainingFor($staff, $leave->start_date->year);
        $this->assertSame(28.0, $balance['entitlement']);
        $this->assertSame(5.0, $balance['taken']);
        $this->assertSame(23.0, $balance['remaining']);
    }

    public function test_staff_cannot_approve_leave(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $leave = $staff->leaveRequests()->create([
            'type' => 'annual',
            'start_date' => today(),
            'end_date' => today(),
            'days' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)->put("/leave/{$leave->id}/review", ['status' => 'approved'])
            ->assertForbidden();
    }
}
