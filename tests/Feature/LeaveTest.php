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

    public function test_leave_days_follow_the_staff_working_pattern(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);

        // Areterra's default working pattern is Mon/Tue/Thu/Fri.
        $this->actingAs($staff)->post('/leave', [
            'type' => 'annual',
            'start_date' => '2026-07-06',
            'end_date' => '2026-07-12',
        ])->assertRedirect();

        $this->assertSame(4.0, (float) LeaveRequest::first()->days);
        Notification::assertSentTo($manager, LeaveSubmitted::class);
    }

    public function test_bank_holidays_and_half_days_are_not_overcharged(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        User::factory()->create(['role' => 'manager']);

        // Summer bank holiday Monday is excluded; Tuesday is half a day.
        $this->actingAs($staff)->post('/leave', [
            'type' => 'annual',
            'start_date' => '2026-08-31',
            'end_date' => '2026-09-04',
            'end_half_day' => true,
        ])->assertRedirect();

        $leave = LeaveRequest::first();
        $this->assertSame(2.5, (float) $leave->days);
        $this->assertSame(['2026' => 2.5], $leave->days_by_year);
    }

    public function test_leave_spanning_new_year_is_split_between_entitlement_years(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($staff)->post('/leave', [
            'type' => 'annual',
            'start_date' => '2026-12-31',
            'end_date' => '2027-01-05',
        ])->assertRedirect();

        $leave = LeaveRequest::first();
        $this->assertSame(['2026' => 1.0, '2027' => 2.0], $leave->days_by_year);

        $this->actingAs($manager)->put("/leave/{$leave->id}/review", ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame(1.0, LeaveBalance::remainingFor($staff, 2026)['taken']);
        $this->assertSame(2.0, LeaveBalance::remainingFor($staff, 2027)['taken']);
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
            'days' => 4,
            'status' => 'pending',
        ]);

        $this->actingAs($manager)->put("/leave/{$leave->id}/review", ['status' => 'approved'])
            ->assertRedirect();

        Notification::assertSentTo($staff, LeaveReviewed::class);

        $balance = LeaveBalance::remainingFor($staff, $leave->start_date->year);
        $this->assertSame(28.0, $balance['entitlement']);
        $this->assertSame(4.0, $balance['taken']);
        $this->assertSame(24.0, $balance['remaining']);
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
