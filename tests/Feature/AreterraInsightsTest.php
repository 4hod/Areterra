<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\DailyMonitoring;
use App\Models\ImpactEntry;
use App\Models\Member;
use App\Models\MemberGoal;
use App\Models\User;
use App\Support\AnimalWelfareBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AreterraInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_record_connected_impact_evidence(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);
        $goal = MemberGoal::create(['member_id' => $member->id, 'title' => 'Build confidence']);
        $animal = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        $this->actingAs($staff)->post("/members/{$member->id}/impact", [
            'observed_at' => now()->toDateTimeString(),
            'member_goal_id' => $goal->id,
            'animal_id' => $animal->id,
            'mood_before' => 'anxious',
            'mood_after' => 'happy',
            'engagement_rating' => 5,
            'independence_rating' => 4,
            'outcome_note' => 'Prepared the feed independently and initiated conversation.',
            'evidence_tags' => ['confidence', 'independence'],
        ])->assertRedirect();

        $entry = ImpactEntry::firstOrFail();
        $this->assertSame($member->id, $entry->member_id);
        $this->assertSame($animal->id, $entry->animal_id);
        $this->assertSame('Prepared the feed independently and initiated conversation.', $entry->outcome_note);
        $this->assertNotSame(
            'Prepared the feed independently and initiated conversation.',
            DB::table('impact_entries')->value('outcome_note'),
            'Impact notes must be encrypted at rest.'
        );
    }

    public function test_impact_entry_requires_authentication_and_capabilities(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $payload = [
            'observed_at' => now()->toDateTimeString(),
            'outcome_note' => 'A valid observation.',
        ];

        $this->post("/members/{$member->id}/impact", $payload)
            ->assertRedirect('/login');

        $volunteer = User::factory()->create(['role' => 'volunteer']);
        $this->actingAs($volunteer)->post("/members/{$member->id}/impact", $payload)
            ->assertForbidden();

        $this->assertDatabaseCount('impact_entries', 0);
    }

    public function test_archived_members_cannot_receive_new_impact_entries(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'first_name' => 'Archived',
            'last_name' => 'Member',
            'status' => 'archived',
        ]);

        $this->actingAs($staff)->post("/members/{$member->id}/impact", [
            'observed_at' => now()->toDateTimeString(),
            'outcome_note' => 'This must not be stored.',
        ])->assertStatus(422);

        $this->assertDatabaseCount('impact_entries', 0);
    }

    public function test_impact_entry_rejects_invalid_scores_moods_and_oversized_tags(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($staff)->post("/members/{$member->id}/impact", [
            'observed_at' => now()->toDateTimeString(),
            'mood_before' => 'invented-mood',
            'engagement_rating' => 6,
            'independence_rating' => 0,
            'outcome_note' => 'Should not save.',
            'evidence_tags' => [str_repeat('x', 51)],
        ])->assertSessionHasErrors([
            'mood_before',
            'engagement_rating',
            'independence_rating',
            'evidence_tags.0',
        ]);

        $this->assertDatabaseCount('impact_entries', 0);
    }

    public function test_impact_goal_must_belong_to_the_member(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $other = Member::create(['first_name' => 'Other', 'last_name' => 'Person']);
        $otherGoal = MemberGoal::create(['member_id' => $other->id, 'title' => 'Other goal']);

        $this->actingAs($staff)->post("/members/{$member->id}/impact", [
            'observed_at' => now()->toDateTimeString(),
            'member_goal_id' => $otherGoal->id,
            'outcome_note' => 'Should not save.',
        ])->assertSessionHasErrors('member_goal_id');

        $this->assertDatabaseCount('impact_entries', 0);
    }

    public function test_day_passport_is_capability_protected_and_contains_working_view(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $member = Member::create([
            'first_name' => 'Amy', 'last_name' => 'Buckle', 'preferred_name' => 'Amy',
            'support_needs' => 'Offer one instruction at a time.', 'allergies' => 'No known allergies',
        ]);

        $this->actingAs($staff)->get("/members/{$member->id}/passport")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Passport')
                ->where('member.name', 'Amy Buckle')
                ->where('member.support_needs', 'Offer one instruction at a time.')
            );
    }

    public function test_day_passport_rejects_guests_and_accounts_without_member_details(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->get("/members/{$member->id}/passport")
            ->assertRedirect('/login');

        $volunteer = User::factory()->create(['role' => 'volunteer']);
        $this->actingAs($volunteer)->get("/members/{$member->id}/passport")
            ->assertForbidden();
    }

    public function test_welfare_baseline_stays_in_learning_mode_without_enough_history(): void
    {
        $user = User::factory()->create();
        $animal = Animal::create(['name' => 'Demon', 'species' => 'Macaw']);

        DailyMonitoring::create([
            'animal_id' => $animal->id,
            'user_id' => $user->id,
            'monitor_date' => today(),
            'weight_grams' => 1000,
            'appetite' => 'good',
            'behaviour' => 'active',
        ]);

        $baseline = AnimalWelfareBaseline::for($animal);
        $this->assertSame('insufficient', $baseline['level']);
        $this->assertSame(0, $baseline['observations']);
        $this->assertSame(today()->toDateString(), $baseline['latest_date']);
    }

    public function test_explicit_welfare_concern_is_always_red_once_baseline_exists(): void
    {
        $user = User::factory()->create();
        $animal = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        foreach (range(1, 3) as $daysAgo) {
            DailyMonitoring::create([
                'animal_id' => $animal->id,
                'user_id' => $user->id,
                'monitor_date' => today()->subDays($daysAgo),
                'appetite' => 'good',
                'behaviour' => 'active',
            ]);
        }

        DailyMonitoring::create([
            'animal_id' => $animal->id,
            'user_id' => $user->id,
            'monitor_date' => today(),
            'appetite' => 'good',
            'behaviour' => 'active',
            'concern' => true,
        ]);

        $baseline = AnimalWelfareBaseline::for($animal);
        $this->assertSame('red', $baseline['level']);
        $this->assertTrue(collect($baseline['signals'])->contains(
            fn ($signal) => $signal['label'] === 'Concern recorded' && $signal['level'] === 'red'
        ));
    }

    public function test_welfare_baseline_flags_explainable_weight_change(): void
    {
        $user = User::factory()->create();
        $animal = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);

        foreach ([1000, 1010, 990, 1005] as $daysAgo => $weight) {
            DailyMonitoring::create([
                'animal_id' => $animal->id,
                'user_id' => $user->id,
                'monitor_date' => today()->subDays($daysAgo + 1),
                'weight_grams' => $weight,
                'appetite' => 'good',
                'behaviour' => 'active',
            ]);
        }
        DailyMonitoring::create([
            'animal_id' => $animal->id,
            'user_id' => $user->id,
            'monitor_date' => today(),
            'weight_grams' => 760,
            'appetite' => 'poor',
            'behaviour' => 'quiet',
        ]);

        $baseline = AnimalWelfareBaseline::for($animal);
        $this->assertSame('red', $baseline['level']);
        $this->assertTrue(collect($baseline['signals'])->contains(fn ($signal) => $signal['label'] === 'Weight' && $signal['level'] === 'red'));
        $this->assertTrue(collect($baseline['signals'])->contains(fn ($signal) => $signal['label'] === 'Appetite' && $signal['level'] === 'amber'));
    }
}
