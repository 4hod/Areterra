<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\TransportLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalQaFixesTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    public function test_care_plan_includes_circle_of_care_contacts(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $member->settings()->create([]);
        $member->contacts()->create([
            'name' => 'Caroline Crumpton',
            'role' => 'Registered manager',
            'organisation' => 'Orbital 4 Support',
            'phone' => '01562 742458',
            'email' => 'ccrumpton@orbital4.com',
        ]);

        $response = $this->actingAs($this->manager())->get("/members/{$member->id}/care-plan")->assertOk();
        $contacts = $response->viewData('page')['props']['member']['circle_of_care'];

        $this->assertCount(1, $contacts);
        $this->assertSame('Caroline Crumpton', $contacts[0]['name']);
    }

    public function test_sar_contains_medical_and_profile_record_domains(): void
    {
        $member = Member::create([
            'first_name' => 'Amy',
            'last_name' => 'Buckle',
            'medical_notes' => 'Medical note',
            'interests' => 'Animal care',
            'medication' => 'Medication details',
        ]);
        $member->settings()->create([]);
        $member->contacts()->create(['name' => 'Emergency Contact', 'phone' => '01234 567890']);
        $member->goals()->create(['title' => 'Independent animal care']);
        $member->alerts()->create(['type' => 'medical', 'text' => 'Carries inhaler', 'severity' => 'red']);

        $response = $this->actingAs($this->manager())->get("/members/{$member->id}/sar")->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('Medical note', $props['member']['medical_notes']);
        $this->assertSame('Animal care', $props['member']['interests']);
        $this->assertCount(1, $props['contacts']);
        $this->assertCount(1, $props['goals']);
        $this->assertCount(1, $props['alerts']);
    }

    public function test_archived_member_is_read_only_in_ui_and_write_endpoints(): void
    {
        $member = Member::create(['first_name' => 'Test', 'last_name' => 'Archived', 'status' => 'archived']);
        $member->settings()->create([]);
        $manager = $this->manager();

        $response = $this->actingAs($manager)->get("/members/{$member->id}")->assertOk();
        $this->assertFalse($response->viewData('page')['props']['canEdit']);

        $this->actingAs($manager)
            ->post("/members/{$member->id}/goals", ['title' => 'Must not be created'])
            ->assertStatus(422);
        $this->actingAs($manager)
            ->post("/members/{$member->id}/abc", [
                'observed_at' => now()->toDateTimeString(),
                'behaviour' => 'Must not be created',
            ])
            ->assertStatus(422);

        $this->assertSame(0, $member->goals()->count());
        $this->assertSame(0, $member->abcObservations()->count());
    }

    public function test_member_medical_notes_and_interests_can_be_updated(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle', 'status' => 'active']);
        $member->settings()->create([]);

        $this->actingAs($this->manager())->put("/members/{$member->id}", [
            'first_name' => 'Amy',
            'last_name' => 'Buckle',
            'status' => 'active',
            'medical_notes' => 'Keep inhaler available',
            'interests' => 'Horses and gardening',
            'gender' => 'Female',
            'allergies' => 'None known',
            'transport_required' => false,
            'attendance_days' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Keep inhaler available', $member->fresh()->medical_notes);
        $this->assertSame('Horses and gardening', $member->fresh()->interests);
        $this->assertSame('Female', $member->fresh()->gender);
        $this->assertSame('None known', $member->fresh()->allergies);
    }

    public function test_consent_decision_requires_provenance_notes(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($this->manager())->post("/members/{$member->id}/consents", [
            'consent_type' => 'photos',
            'granted' => true,
        ])->assertSessionHasErrors('notes');

        $this->assertSame(0, $member->consents()->count());
    }

    public function test_referral_requires_contact_authority_and_privacy_confirmation(): void
    {
        $this->post('/refer', [
            'referrer_name' => 'Jane Social Worker',
            'person_name' => 'John Smith',
        ])->assertSessionHasErrors(['referrer_email', 'referrer_phone', 'authority_confirmed', 'privacy_acknowledged']);

        $this->post('/refer', [
            'referrer_name' => 'Jane Social Worker',
            'referrer_email' => 'jane@example.test',
            'person_name' => 'John Smith',
            'authority_confirmed' => true,
            'privacy_acknowledged' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('referrals', 1);
    }

    public function test_global_search_returns_member_json(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $this->actingAs($this->manager())
            ->getJson('/search?q=Amy')
            ->assertOk()
            ->assertJsonPath('results.Members.0.title', 'Amy Buckle')
            ->assertJsonPath('results.Members.0.url', "/members/{$member->id}");
    }

    public function test_dashboard_and_checklist_agree_when_an_animal_was_checked_but_not_fed(): void
    {
        $animal = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);
        $staff = User::factory()->create(['role' => 'staff']);
        $animal->welfareChecks()->create([
            'user_id' => $staff->id,
            'status' => 'amber',
            'concern' => true,
            'fed' => false,
            'treats_given' => false,
        ]);

        $response = $this->actingAs($staff)->get('/')->assertOk();
        $props = $response->viewData('page')['props'];
        $welfare = collect($props['checklist'])->firstWhere('key', 'welfare');

        $this->assertSame(1, $props['stats']['animalsNeedingChecks']);
        $this->assertFalse($welfare['done']);
    }

    public function test_species_welfare_check_rejects_implicit_or_incomplete_group_results(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $first = Animal::create(['name' => 'Rico', 'species' => 'Macaw']);
        Animal::create(['name' => 'Angel', 'species' => 'Macaw']);

        $this->actingAs($staff)->post('/welfare-checks/species', [
            'species' => 'Macaw',
            'verification_confirmed' => true,
            'checks' => [[
                'animal_id' => $first->id,
                'status' => null,
                'fed' => true,
                'treats_given' => false,
            ]],
        ])->assertSessionHasErrors('checks');

        $this->assertDatabaseCount('welfare_checks', 0);
    }

    public function test_transport_ledger_creation_writes_one_audit_entry(): void
    {
        $member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);

        $entry = TransportLedgerEntry::create([
            'member_id' => $member->id,
            'type' => 'payment',
            'amount' => 5,
            'entry_date' => today(),
        ]);

        $this->assertSame(1, AuditLog::where('subject_type', 'TransportLedgerEntry')
            ->where('subject_id', $entry->id)
            ->where('action', 'created')
            ->count());
    }
}
