<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\TransportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWorkflowOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Member $member;

    private Animal $animal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->animal = Animal::create(['name' => 'Demon', 'species' => 'Macaw']);
        $this->member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $this->member->settings()->create([
            'attendance_days' => [today()->isoWeekday()],
            'transport_required' => true,
        ]);
    }

    public function test_dashboard_remains_available_as_the_home_page(): void
    {
        $this->actingAs($this->staff)->get('/')->assertOk();
    }

    public function test_register_cannot_be_opened_or_changed_before_transport(): void
    {
        $this->actingAs($this->staff)->get('/register')
            ->assertRedirect('/today')
            ->assertSessionHas('error');

        $this->actingAs($this->staff)->post("/register/{$this->member->id}/check-in", [
            'arrival_mood' => 'happy',
        ])->assertRedirect('/today');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_transport_is_available_even_before_welfare_and_feeding_are_complete(): void
    {
        $this->actingAs($this->staff)->get('/transport')->assertOk();
    }

    public function test_feeding_can_be_logged_before_any_other_daily_job(): void
    {
        $this->actingAs($this->staff)->post("/animals/{$this->animal->id}/welfare-checks", [
            'status' => 'green',
            'fed' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('welfare_checks', [
            'animal_id' => $this->animal->id,
            'fed' => true,
        ]);
    }

    public function test_register_unlocks_after_transport_is_recorded(): void
    {
        TransportRun::create([
            'run_date' => today(),
            'member_id' => $this->member->id,
            'phase' => 'morning',
            'outcome' => 'collected',
        ]);

        $this->actingAs($this->staff)->post("/register/{$this->member->id}/check-in", [
            'arrival_mood' => 'happy',
        ])->assertRedirect();

        $this->assertTrue((bool) Attendance::first()?->checked_in);
    }

    public function test_end_of_day_cannot_be_saved_while_an_earlier_job_is_open(): void
    {
        $this->actingAs($this->staff)->post("/end-of-day/{$this->member->id}", [
            'end_mood' => 'happy',
        ])->assertRedirect('/today');

        $this->assertDatabaseCount('end_of_day_records', 0);
    }
}
