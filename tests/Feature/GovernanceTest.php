<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Announcement;
use App\Models\Member;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use App\Notifications\ConcernRaised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_notifies_all_other_staff(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($manager)->post('/announcements', [
            'title' => 'New rota',
            'body' => 'The August rota is out.',
        ])->assertRedirect();

        Notification::assertSentTo($staff, AnnouncementPosted::class);
        Notification::assertNotSentTo($manager, AnnouncementPosted::class);
    }

    public function test_welfare_concern_notifies_managers(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);
        $animal = Animal::create(['name' => 'Demon', 'species' => 'Macaw']);

        $this->actingAs($staff)->post("/animals/{$animal->id}/welfare-checks", [
            'status' => 'amber',
            'concern' => true,
            'notes' => 'Off his food',
        ])->assertRedirect();

        Notification::assertSentTo($manager, ConcernRaised::class);
        Notification::assertNotSentTo($staff, ConcernRaised::class);
    }

    public function test_end_of_day_concern_notifies_managers_once(): void
    {
        Notification::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $manager = User::factory()->create(['role' => 'manager']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $payload = [
            'end_mood' => 'sad',
            'concern' => true,
            'concern_detail' => 'Very withdrawn today',
        ];
        $this->actingAs($staff)->post("/end-of-day/{$member->id}", $payload)->assertRedirect();
        // Re-saving the same record with concern still set must not re-notify.
        $this->actingAs($staff)->post("/end-of-day/{$member->id}", [...$payload, 'notes' => 'edited'])->assertRedirect();

        Notification::assertSentToTimes($manager, ConcernRaised::class, 1);
    }

    public function test_audit_surfaces_overdue_and_missing_items(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']); // no review, no sessions
        Animal::create(['name' => 'Demon', 'species' => 'Macaw', 'welfare_status' => 'red']); // no vet record + red

        $response = $this->actingAs($manager)->get('/audit')->assertOk();

        $page = $response->viewData('page')['props'];
        $messages = collect($page['findings'])->pluck('message');

        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'no review scheduled')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'no vet record')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'welfare status is red')));
        $this->assertGreaterThan(0, $page['counts']['critical']);
    }

    public function test_staff_cannot_view_audit_or_post_announcements(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/audit')->assertForbidden();
        $this->actingAs($staff)->post('/announcements', ['title' => 'X', 'body' => 'Y'])->assertForbidden();
    }
}
