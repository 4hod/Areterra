<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every remaining staff-facing module, exercised through its real route with
 * real input, then checked that the record actually persisted.
 *
 * A manager exists in every case on purpose: several of these notify managers
 * on save, and that path only runs when there is somebody to notify.
 */
class StaffModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->admin = User::factory()->create(['role' => 'administrator']);
    }

    private function member(): Member
    {
        return Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
    }

    private function animal(): Animal
    {
        return Animal::create(['name' => 'Rico', 'species' => 'Macaw']);
    }

    public function test_a_session_is_saved(): void
    {
        $this->actingAs($this->staff)->post('/activities', [
            'title' => 'Animal handling',
            'activity_date' => today()->toDateString(),
            'start_time' => '10:00',
            'description' => 'Small group session.',
        ])->assertRedirect();

        $this->assertDatabaseHas('activities', ['title' => 'Animal handling']);
    }

    public function test_an_animal_is_saved(): void
    {
        $this->actingAs($this->manager)->post('/animals', [
            'name' => 'Pip',
            'species' => 'Chinchilla',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('animals', ['name' => 'Pip']);
    }

    public function test_daily_monitoring_is_saved_against_the_animal(): void
    {
        $rico = $this->animal();

        $this->actingAs($this->staff)->post("/animals/{$rico->id}/monitoring", [
            'monitor_date' => today()->toDateString(),
            'appetite' => 'good',
            'behaviour' => 'Bright and active',
            'concern' => false,
        ])->assertRedirect();

        $this->assertSame(1, $rico->dailyMonitoring()->count());
    }

    public function test_a_compliance_item_is_saved_and_can_be_completed(): void
    {
        $this->actingAs($this->manager)->post('/compliance', [
            'title' => 'Fire risk assessment',
            'category' => 'safety',
            'due_date' => today()->addMonth()->toDateString(),
        ])->assertRedirect();

        $item = \App\Models\ComplianceItem::first();
        $this->assertNotNull($item, 'The compliance item was not saved.');

        $this->actingAs($this->manager)->post("/compliance/{$item->id}/complete")->assertRedirect();
        $this->assertNotNull($item->fresh()->completed_at);
    }

    public function test_an_end_of_day_record_is_saved_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->staff)->post("/end-of-day/{$amy->id}", [
            'end_mood' => 'happy',
            'session_type' => 'full-day',
            'notes' => 'Good day, enjoyed the birds.',
            'concern' => false,
        ])->assertRedirect();

        $this->assertSame(1, $amy->endOfDayRecords()->count());
    }

    public function test_a_safeguarding_concern_is_saved(): void
    {
        $amy = $this->member();

        // Safeguarding sits behind password.confirm — without this the request
        // is redirected to the confirmation screen and nothing is saved.
        $this->actingAs($this->admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/safeguarding', [
            'member_id' => $amy->id,
            'date' => today()->toDateString(),
            'details' => 'Disclosure during session.',
            'actions_taken' => 'Escalated to the safeguarding lead.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('safeguarding_concerns', ['member_id' => $amy->id]);
    }

    public function test_a_risk_assessment_is_saved_and_can_be_signed_off(): void
    {
        $this->actingAs($this->manager)->post('/risk-assessments', [
            'title' => 'Working with large birds',
            'description' => 'Handling macaws in the aviary.',
            'likelihood' => 2,
            'severity' => 4,
            'control_measures' => 'Gloves, two staff present.',
            'status' => 'active',
        ])->assertRedirect();

        $ra = \App\Models\RiskAssessment::first();
        $this->assertNotNull($ra, 'The risk assessment was not saved.');

        $this->actingAs($this->manager)->post("/risk-assessments/{$ra->id}/sign-off")->assertRedirect();
    }

    public function test_a_member_review_is_saved(): void
    {
        $amy = $this->member();

        $this->actingAs($this->manager)->post('/reviews', [
            'member_id' => $amy->id,
            'review_date' => today()->toDateString(),
            'outcomes' => 'Confidence improving.',
            'actions' => 'Continue with animal handling.',
        ])->assertRedirect();

        $this->assertDatabaseHas('member_reviews', ['member_id' => $amy->id]);
    }

    public function test_a_supervision_is_saved(): void
    {
        $this->actingAs($this->manager)->post('/supervisions', [
            'subject_key' => 'user:'.$this->staff->id,
            'type' => 'supervision',
            'date' => today()->toDateString(),
            'discussion' => 'Settling in well.',
            'actions_agreed' => 'Shadow a transport run.',
        ])->assertRedirect();

        $this->assertDatabaseCount('supervisions', 1);
    }

    public function test_a_leave_request_is_saved(): void
    {
        $this->actingAs($this->staff)->post('/leave', [
            'type' => 'annual',
            'start_date' => today()->addWeek()->toDateString(),
            'end_date' => today()->addWeek()->addDays(2)->toDateString(),
            'reason' => 'Family visit',
        ])->assertRedirect();

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_a_policy_is_saved(): void
    {
        $this->actingAs($this->admin)->post('/policies', [
            'title' => 'Lone working',
            'body' => 'Staff must not work alone with animals after dark.',
            'version' => '1.0',
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('policies', ['title' => 'Lone working']);
    }

    public function test_an_announcement_is_saved(): void
    {
        $this->actingAs($this->manager)->post('/announcements', [
            'title' => 'Team meeting Friday',
            'body' => '9am in the barn.',
        ])->assertRedirect();

        $this->assertDatabaseHas('announcements', ['title' => 'Team meeting Friday']);
    }

    public function test_a_funding_opportunity_is_saved(): void
    {
        $this->actingAs($this->manager)->post('/funding', [
            'title' => 'Community fund',
            'funder' => 'Local Trust',
            'amount' => 5000,
            'status' => 'identified',
        ])->assertRedirect();

        $this->assertDatabaseHas('funding_opportunities', ['title' => 'Community fund']);
    }

    public function test_an_insurance_policy_is_saved(): void
    {
        $this->actingAs($this->admin)->post('/insurance', [
            'policy_type' => 'public liability',
            'provider' => 'Example Insurance',
            'renewal_date' => today()->addYear()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseCount('insurance_policies', 1);
    }

    public function test_a_sar_request_is_saved(): void
    {
        $amy = $this->member();

        $this->actingAs($this->admin)->post('/sar-requests', [
            'requester_name' => 'Jane Buckle',
            'requester_relationship' => 'Mother',
            'member_id' => $amy->id,
            'received_date' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseCount('sar_requests', 1);
    }

    public function test_a_referral_is_saved(): void
    {
        $this->post('/refer', [
            'referrer_name' => 'Dr Smith',
            'referrer_email' => 'smith@example.com',
            'person_name' => 'New Person',
            'details' => 'Would benefit from animal-assisted sessions.',
        ])->assertRedirect();

        $this->assertDatabaseCount('referrals', 1);
    }

    public function test_a_member_goal_and_outcome_are_saved_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->staff)->post("/members/{$amy->id}/goals", [
            'title' => 'Handle a bird unaided',
            'target_date' => today()->addMonths(3)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, $amy->goals()->count());
    }

    public function test_a_member_contact_is_saved_against_the_member(): void
    {
        $amy = $this->member();

        $this->actingAs($this->staff)->post("/members/{$amy->id}/contacts", [
            'name' => 'Jane Buckle',
            'relationship' => 'Mother',
            'phone' => '01234 567890',
        ])->assertRedirect();

        $this->assertSame(1, $amy->contacts()->count());
    }
}
