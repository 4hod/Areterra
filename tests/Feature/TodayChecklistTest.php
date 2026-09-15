<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\TransportRun;
use App\Models\User;
use App\Services\TodayChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function checklist(): array
    {
        return collect(app(TodayChecklist::class)->build(today()))
            ->keyBy('key')
            ->map(fn ($item) => $item['done'])
            ->all();
    }

    public function test_empty_transport_is_skipped_but_daily_records_remain_open(): void
    {
        Animal::create(['name' => 'Demon', 'species' => 'Macaw']);

        $done = $this->checklist();

        $this->assertTrue($done['transport']);
        $this->assertFalse($done['register']);
        $this->assertFalse($done['moods']);
        $this->assertFalse($done['welfare']);
        $this->assertFalse($done['end_of_day']);
    }

    public function test_register_requires_checked_in_not_just_scheduled(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        Attendance::create(['member_id' => $member->id, 'date' => today(), 'checked_in' => false]);

        $this->assertFalse($this->checklist()['register']);

        Attendance::query()->update(['checked_in' => true]);

        $this->assertTrue($this->checklist()['register']);
    }

    public function test_moods_require_every_attendee_to_have_one(): void
    {
        $a = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $b = Member::create(['first_name' => 'Billy', 'last_name' => 'Garrett']);
        Attendance::create(['member_id' => $a->id, 'date' => today(), 'checked_in' => true, 'arrival_mood' => 'happy']);
        Attendance::create(['member_id' => $b->id, 'date' => today(), 'checked_in' => true]);

        $this->assertFalse($this->checklist()['moods']);

        Attendance::where('member_id', $b->id)->update(['arrival_mood' => 'neutral']);

        $this->assertTrue($this->checklist()['moods']);
    }

    public function test_welfare_requires_every_active_animal_checked_and_fed_today(): void
    {
        $user = User::factory()->create();
        $demon = Animal::create(['name' => 'Demon', 'species' => 'Macaw']);
        $angel = Animal::create(['name' => 'Angel', 'species' => 'Macaw']);
        Animal::create(['name' => 'Retired', 'species' => 'Rabbit', 'status' => 'inactive']);

        $demonCheck = $demon->welfareChecks()->create(['user_id' => $user->id, 'status' => 'green', 'fed' => false]);

        $this->assertFalse($this->checklist()['welfare']);

        // Checking the other animal is not enough while Demon is still unfed.
        $angel->welfareChecks()->create(['user_id' => $user->id, 'status' => 'green', 'fed' => true]);

        $this->assertFalse($this->checklist()['welfare']);

        $demonCheck->update(['fed' => true]);

        $this->assertTrue($this->checklist()['welfare']);
    }

    public function test_end_of_day_requires_a_record_for_every_attendee(): void
    {
        $user = User::factory()->create();
        $a = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $b = Member::create(['first_name' => 'Billy', 'last_name' => 'Garrett']);
        Attendance::create(['member_id' => $a->id, 'date' => today(), 'checked_in' => true]);
        Attendance::create(['member_id' => $b->id, 'date' => today(), 'checked_in' => true]);

        EndOfDayRecord::create(['member_id' => $a->id, 'date' => today(), 'user_id' => $user->id]);

        $this->assertFalse($this->checklist()['end_of_day']);

        EndOfDayRecord::create(['member_id' => $b->id, 'date' => today(), 'user_id' => $user->id]);

        $this->assertTrue($this->checklist()['end_of_day']);
    }

    public function test_transport_done_when_any_run_recorded(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->settings()->create([
            'attendance_days' => [today()->isoWeekday()],
            'transport_required' => true,
        ]);

        $this->assertFalse($this->checklist()['transport']);

        TransportRun::create(['run_date' => today(), 'member_id' => $member->id, 'phase' => 'morning']);

        $this->assertTrue($this->checklist()['transport']);
        $this->assertFalse($this->checklist()['return_transport']);

        TransportRun::create(['run_date' => today(), 'member_id' => $member->id, 'phase' => 'afternoon']);

        $this->assertTrue($this->checklist()['return_transport']);
    }

    public function test_return_transport_is_the_final_ordered_step(): void
    {
        $user = User::factory()->create();
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->settings()->create([
            'attendance_days' => [today()->isoWeekday()],
            'transport_required' => true,
        ]);
        TransportRun::create(['run_date' => today(), 'member_id' => $member->id, 'phase' => 'morning']);
        Attendance::create([
            'member_id' => $member->id,
            'date' => today(),
            'checked_in' => true,
            'arrival_mood' => 'happy',
        ]);

        $items = collect(app(TodayChecklist::class)->build(today()))->keyBy('key');
        $this->assertFalse($items['return_transport']['available']);
        $this->assertFalse($items['return_transport']['done']);

        EndOfDayRecord::create(['member_id' => $member->id, 'date' => today(), 'user_id' => $user->id]);

        $items = collect(app(TodayChecklist::class)->build(today()))->keyBy('key');
        $this->assertTrue($items['return_transport']['available']);
        $this->assertFalse($items['return_transport']['done']);
    }

    public function test_welfare_is_parallel_while_the_other_steps_are_sequential(): void
    {
        $user = User::factory()->create();
        $animal = Animal::create(['name' => 'Demon', 'species' => 'Macaw']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->settings()->create([
            'attendance_days' => [today()->isoWeekday()],
            'transport_required' => true,
        ]);

        $items = collect(app(TodayChecklist::class)->build(today()))->keyBy('key');

        $this->assertTrue($items['welfare']['available']);
        $this->assertTrue($items['transport']['available']);
        $this->assertFalse($items['register']['available']);
        $this->assertFalse($items['end_of_day']['available']);

        $animal->welfareChecks()->create(['user_id' => $user->id, 'status' => 'green', 'fed' => true]);

        TransportRun::create(['run_date' => today(), 'member_id' => $member->id, 'phase' => 'morning']);

        $items = collect(app(TodayChecklist::class)->build(today()))->keyBy('key');
        $this->assertTrue($items['register']['available']);
        $this->assertFalse($items['moods']['available']);
    }
}
