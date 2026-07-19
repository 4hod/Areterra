<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_administrator_holds_every_capability(): void
    {
        $this->actingAs($this->user('administrator'));

        $this->assertTrue(Gate::allows('edit_members'));
        $this->assertTrue(Gate::allows('access_safeguarding'));
        $this->assertTrue(Gate::allows('approve_leave'));
    }

    public function test_manager_can_edit_members_but_not_access_safeguarding(): void
    {
        $this->actingAs($this->user('manager'));

        $this->assertTrue(Gate::allows('edit_members'));
        $this->assertTrue(Gate::allows('approve_leave'));
        $this->assertFalse(Gate::allows('access_safeguarding'));
    }

    public function test_staff_can_view_but_not_edit_members(): void
    {
        $this->actingAs($this->user('staff'));

        $this->assertTrue(Gate::allows('view_members'));
        $this->assertTrue(Gate::allows('log_welfare'));
        $this->assertFalse(Gate::allows('edit_members'));
        $this->assertFalse(Gate::allows('access_safeguarding'));
    }

    public function test_safeguarding_lead_has_staff_capabilities_plus_safeguarding(): void
    {
        $this->actingAs($this->user('safeguarding_lead'));

        $this->assertTrue(Gate::allows('view_members'));
        $this->assertTrue(Gate::allows('access_safeguarding'));
        $this->assertFalse(Gate::allows('edit_members'));
    }

    public function test_routes_enforce_capabilities(): void
    {
        $member = Member::create(['first_name' => 'Test', 'last_name' => 'Member']);

        $this->actingAs($this->user('staff'))
            ->put("/members/{$member->id}", ['first_name' => 'X', 'last_name' => 'Y', 'status' => 'active'])
            ->assertForbidden();

        $this->actingAs($this->user('manager'))
            ->put("/members/{$member->id}", ['first_name' => 'X', 'last_name' => 'Y', 'status' => 'active'])
            ->assertRedirect();
    }

    public function test_volunteer_does_not_see_sensitive_member_details(): void
    {
        $member = Member::create([
            'first_name' => 'Test',
            'last_name' => 'Member',
            'nhs_number' => '123 456 7890',
        ]);

        $this->actingAs($this->user('volunteer'))
            ->get("/members/{$member->id}")
            ->assertOk()
            ->assertDontSee('123 456 7890');

        $this->actingAs($this->user('staff'))
            ->get("/members/{$member->id}")
            ->assertOk()
            ->assertSee('123 456 7890');
    }
}
