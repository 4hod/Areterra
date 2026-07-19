<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_creates_todays_attendance_with_mood(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($user)->post("/register/{$member->id}/check-in", [
            'arrival_mood' => 'happy',
        ])->assertRedirect();

        $attendance = Attendance::whereDate('date', today())->where('member_id', $member->id)->first();
        $this->assertTrue($attendance->checked_in);
        $this->assertSame('happy', $attendance->arrival_mood);
        $this->assertNotNull($attendance->checked_in_at);
    }

    public function test_checking_in_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($user)->post("/register/{$member->id}/check-in", ['arrival_mood' => 'happy']);
        $this->actingAs($user)->post("/register/{$member->id}/check-in", ['arrival_mood' => 'neutral']);

        $this->assertSame(1, Attendance::where('member_id', $member->id)->count());
        $this->assertSame('neutral', Attendance::first()->arrival_mood);
    }

    public function test_end_of_day_copies_arrival_mood_and_requires_concern_detail(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($user)->post("/register/{$member->id}/check-in", ['arrival_mood' => 'happy']);

        // Concern without detail is rejected.
        $this->actingAs($user)->post("/end-of-day/{$member->id}", [
            'end_mood' => 'neutral',
            'concern' => true,
        ])->assertSessionHasErrors('concern_detail');

        $this->actingAs($user)->post("/end-of-day/{$member->id}", [
            'end_mood' => 'neutral',
            'session_type' => 'Animal care',
            'concern' => true,
            'concern_detail' => 'Seemed withdrawn this afternoon',
        ])->assertRedirect();

        $record = $member->endOfDayRecords()->first();
        $this->assertSame('happy', $record->arrival_mood);
        $this->assertSame('neutral', $record->end_mood);
        $this->assertTrue($record->concern);
    }
}
