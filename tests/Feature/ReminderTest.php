<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Notifications\DailyReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scheduleMember(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->settings()->create(['attendance_days' => [1, 2, 4, 5]]);
    }

    public function test_register_reminder_fires_once_on_operating_day_with_no_register(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00'); // Monday
        Queue::fake();

        $manager = User::factory()->create(['role' => 'manager']);
        User::factory()->create(['role' => 'staff']);
        $this->scheduleMember();

        $this->artisan('hub:remind-register')->assertSuccessful();
        // Second run the same day must dedupe.
        $this->artisan('hub:remind-register')->assertSuccessful();

        $this->assertNotificationQueuedTo($manager, DailyReminder::class);
        $this->assertUniqueNotificationCount(DailyReminder::class, 1);
    }

    public function test_no_reminder_on_wednesday_or_weekend(): void
    {
        Queue::fake();
        User::factory()->create(['role' => 'manager']);
        $this->scheduleMember();

        foreach (['2026-07-22', '2026-07-25', '2026-07-26'] as $closedDay) { // Wed, Sat, Sun
            Carbon::setTestNow("{$closedDay} 12:00:00");
            $this->artisan('hub:remind-register')->assertSuccessful();
        }

        Queue::assertNothingPushed();
    }

    public function test_no_reminder_when_register_is_done(): void
    {
        Carbon::setTestNow('2026-07-20 12:00:00'); // Monday
        Queue::fake();
        User::factory()->create(['role' => 'manager']);
        $this->scheduleMember();

        Member::first()->attendances()->create(['date' => today(), 'checked_in' => true]);

        $this->artisan('hub:remind-register')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_end_of_day_reminder_counts_missing_records(): void
    {
        Carbon::setTestNow('2026-07-20 14:30:00'); // Monday
        Queue::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->scheduleMember();

        Member::first()->attendances()->create(['date' => today(), 'checked_in' => true]);

        $this->artisan('hub:remind-end-of-day')->assertSuccessful();

        $this->assertNotificationQueuedTo($manager, DailyReminder::class);
    }
}
