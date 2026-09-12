<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Incident;
use App\Models\MaintenanceTask;
use App\Models\Member;
use App\Models\ProductOrder;
use App\Models\Recognition;
use App\Models\Task;
use App\Models\TimeclockEntry;
use App\Models\User;
use App\Models\WelfareCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The things staff will actually do in week one — filled in through the real
 * routes, then checked that the data landed where it should and that anything
 * meant to follow actually followed.
 */
class DailyWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->manager = User::factory()->create(['role' => 'manager']);
    }

    // ---------------------------------------------------------- incidents

    public function test_logging_an_incident_saves_it_and_records_who_reported_it(): void
    {
        $this->actingAs($this->staff)->post('/incidents', [
            'title' => 'Slip in the yard',
            'occurred_at' => now()->toDateTimeString(),
            'description' => 'Wet leaves by the gate.',
            'severity' => 'moderate',
            'location' => 'Yard',
        ])->assertRedirect();

        $incident = Incident::first();

        $this->assertNotNull($incident, 'The incident was not saved.');
        $this->assertSame('Slip in the yard', $incident->title);
        $this->assertSame($this->staff->id, $incident->reported_by);
    }

    public function test_an_incident_about_an_animal_raises_a_follow_up_on_that_animal(): void
    {
        $rico = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        $incident = Incident::create([
            'title' => 'Enclosure damage',
            'occurred_at' => now(),
            'description' => 'Mesh panel loose.',
            'severity' => 'serious',
            'reported_by' => $this->staff->id,
            'subject_type' => Animal::class,
            'subject_id' => $rico->id,
        ]);

        \App\Events\IncidentLogged::dispatch($incident);

        $task = $rico->openTasks()->first();

        $this->assertNotNull($task, 'No follow-up task was raised on the animal.');
        $this->assertSame('high', $task->priority);
        $this->assertStringContainsString('Enclosure damage', $task->title);
    }

    // ------------------------------------------------------------- animals

    public function test_a_welfare_concern_raises_a_vet_follow_up_task(): void
    {
        $rico = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        $this->actingAs($this->staff)->post("/animals/{$rico->id}/welfare-checks", [
            'status' => 'amber',
            'concern' => true,
            'notes' => 'Off his food.',
        ])->assertRedirect();

        $this->assertSame(1, $rico->welfareChecks()->count());
        $this->assertTrue($rico->welfareChecks()->first()->concern);

        $task = $rico->openTasks()->first();
        $this->assertNotNull($task, 'A concern should raise a vet follow-up.');
        $this->assertSame('vet_record_created', $task->completes_on_event);
    }

    public function test_adding_a_vet_record_saves_it_against_the_animal(): void
    {
        $rico = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        $this->actingAs($this->staff)->post("/animals/{$rico->id}/vet-records", [
            'visit_date' => today()->toDateString(),
            'reason' => 'Feather loss',
            'treatment' => 'Supplement course',
            'vet_name' => 'Mr Patel',
        ])->assertRedirect();

        $this->assertSame(1, $rico->vetRecords()->count());
        $this->assertSame('Feather loss', $rico->vetRecords()->first()->reason);
    }

    // ------------------------------------------------------------ register

    public function test_checking_a_member_in_records_them_present(): void
    {
        $amy = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $amy->settings()->create(['attendance_days' => [today()->isoWeekday()]]);

        $this->actingAs($this->staff)->post("/register/{$amy->id}/check-in", [
            'arrival_mood' => 'happy',
        ])->assertRedirect();

        $attendance = $amy->attendances()->whereDate('date', today())->first();

        $this->assertNotNull($attendance, 'No attendance row was created.');
        $this->assertTrue((bool) $attendance->checked_in);
        $this->assertSame('present', $attendance->status);
    }

    public function test_marking_a_member_absent_stands_down_their_afternoon_transport(): void
    {
        $amy = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $amy->settings()->create(['attendance_days' => [today()->isoWeekday()], 'transport_required' => true]);

        \App\Models\TransportRun::create([
            'run_date' => today(), 'member_id' => $amy->id,
            'phase' => 'afternoon', 'outcome' => 'not_collected',
        ]);

        $this->actingAs($this->staff)->post("/register/{$amy->id}/absent", [
            'absence_reason' => 'Unwell',
        ])->assertRedirect();

        $attendance = $amy->attendances()->whereDate('date', today())->first();
        $this->assertSame('absent', $attendance->status);

        $this->assertSame(
            'absent',
            \App\Models\TransportRun::where('member_id', $amy->id)->where('phase', 'afternoon')->first()->outcome,
            'The afternoon run should be stood down when a member is marked absent.',
        );
    }

    // --------------------------------------------------------- maintenance

    public function test_a_maintenance_job_is_saved_and_can_be_completed(): void
    {
        $this->actingAs($this->manager)->post('/maintenance', [
            'title' => 'Fix gate latch',
            'priority' => 'high',
            'due_date' => today()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $job = MaintenanceTask::first();
        $this->assertNotNull($job, 'The maintenance job was not saved.');

        $this->actingAs($this->manager)->post("/maintenance/{$job->id}/complete")->assertRedirect();
        $this->assertNotNull($job->fresh()->completed_at);
    }

    // --------------------------------------------------------------- orders

    public function test_a_product_request_is_saved_as_pending(): void
    {
        $this->actingAs($this->staff)->post('/orders', [
            'item_name' => 'Parrot pellets',
            'quantity' => 2,
            'unit' => 'sacks',
        ])->assertRedirect();

        $order = ProductOrder::first();

        $this->assertNotNull($order, 'The order was not saved.');
        $this->assertSame('pending', $order->status);
        $this->assertSame($this->staff->id, $order->requested_by);
    }

    // ----------------------------------------------------------- timeclock

    public function test_clocking_in_and_out_records_a_shift(): void
    {
        $this->actingAs($this->staff)->post('/timeclock/in')->assertRedirect();

        $entry = TimeclockEntry::where('user_id', $this->staff->id)->first();
        $this->assertNotNull($entry, 'No timeclock entry was created.');
        $this->assertNull($entry->clock_out);

        $this->actingAs($this->staff)->post('/timeclock/out')->assertRedirect();
        $this->assertNotNull($entry->fresh()->clock_out, 'Clocking out did not close the shift.');
    }

    // ---------------------------------------------------------- recognition

    public function test_a_shoutout_is_posted_against_its_author(): void
    {
        $this->actingAs($this->staff)->post('/recognition', [
            'recipient' => 'Lucy',
            'message' => 'Brilliant with the new starter today.',
        ])->assertRedirect();

        $recognition = Recognition::first();

        $this->assertNotNull($recognition, 'The shoutout was not saved.');
        $this->assertSame($this->staff->id, $recognition->user_id);
    }

    // --------------------------------------------------------------- tasks

    public function test_a_task_can_be_added_and_completed_from_the_task_list(): void
    {
        $this->actingAs($this->staff)->post('/tasks', [
            'title' => 'Order more bedding',
            'priority' => 'medium',
        ])->assertRedirect();

        $task = Task::first();
        $this->assertNotNull($task, 'The task was not saved.');

        $this->actingAs($this->staff)->post("/tasks/{$task->id}/complete")->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
    }
}
