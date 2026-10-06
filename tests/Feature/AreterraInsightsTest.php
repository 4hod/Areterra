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
