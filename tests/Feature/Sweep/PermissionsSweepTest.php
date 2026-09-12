<?php

namespace Tests\Feature\Sweep;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PermissionsSweepTest extends SweepTestCase
{
    public function test_a_new_account_starts_from_its_role_preset(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertEqualsCanonicalizing(
            User::preset('staff'),
            $staff->capabilities(),
            'new account did not inherit its preset'
        );
        $this->assertTrue($staff->hasCapability('log_sessions'));
        $this->assertFalse($staff->hasCapability('manage_finance'));
    }

    public function test_permissions_are_held_per_account_not_by_role(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->assertFalse($staff->hasCapability('manage_payroll'));

        // Give this one person payroll without inventing a new role.
        $staff->syncCapabilities([...$staff->capabilities(), 'manage_payroll'], $this->admin);

        $this->assertTrue($staff->fresh()->hasCapability('manage_payroll'));
        $this->assertTrue($staff->fresh()->hasCapability('log_sessions'), 'existing permissions were lost');

        // Their colleague on the same role is unaffected.
        $colleague = User::factory()->create(['role' => 'staff']);
        $this->assertFalse($colleague->hasCapability('manage_payroll'));
    }

    public function test_a_granted_capability_actually_opens_the_route(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/payroll')->assertForbidden();

        $staff->syncCapabilities([...$staff->capabilities(), 'manage_payroll'], $this->admin);

        $this->actingAs($staff->fresh())->get('/payroll')->assertOk();
    }

    public function test_removing_a_capability_closes_the_route(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->get('/finance')->assertOk();

        $manager->syncCapabilities(
            array_values(array_diff($manager->capabilities(), ['manage_finance'])),
            $this->admin
        );

        $this->actingAs($manager->fresh())->get('/finance')->assertForbidden();
    }

    public function test_administrators_hold_real_grants_rather_than_a_bypass(): void
    {
        // The old Gate::before shortcut is gone, so an admin's access is visible
        // in the same table as everyone else's — and auditable.
        $this->assertCount(
            count(config('capabilities.all')),
            $this->admin->capabilities(),
            'administrator did not receive the full preset'
        );
        $this->assertSame(
            count(config('capabilities.all')),
            DB::table('user_capabilities')->where('user_id', $this->admin->id)->count()
        );

        // And the direct-call path agrees with the gate (the bug this replaces).
        $this->assertTrue($this->admin->hasCapability('manage_finance'));
        $this->assertTrue($this->admin->can('manage_finance'));
    }

    public function test_permissions_screen_updates_a_users_access(): void
    {
        $this->get('/settings/permissions')->assertOk();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertWriteOk($this->put("/settings/permissions/{$staff->id}", [
            'capabilities' => ['access_hub', 'view_members', 'manage_orders'],
        ]), 'permissions.update');

        $this->assertEqualsCanonicalizing(
            ['access_hub', 'view_members', 'manage_orders'],
            $staff->fresh()->capabilities()
        );
        // Who granted it is recorded.
        $this->assertSame(
            $this->admin->id,
            DB::table('user_capabilities')->where('user_id', $staff->id)
                ->where('capability', 'manage_orders')->first()->granted_by
        );
    }

    public function test_unknown_capabilities_are_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->put("/settings/permissions/{$staff->id}", [
            'capabilities' => ['access_hub', 'become_root'],
        ])->assertSessionHasErrors('capabilities.1');

        $this->assertFalse(
            DB::table('user_capabilities')->where('capability', 'become_root')->exists(),
            'an unknown capability was stored'
        );
    }

    public function test_applying_a_preset_replaces_the_whole_set(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $staff->syncCapabilities([...$staff->capabilities(), 'manage_finance'], $this->admin);

        $this->assertWriteOk($this->post("/settings/permissions/{$staff->id}/preset", [
            'role' => 'volunteer',
        ]), 'permissions.preset');

        $this->assertEqualsCanonicalizing(User::preset('volunteer'), $staff->fresh()->capabilities());
        $this->assertFalse($staff->fresh()->hasCapability('manage_finance'), 'preset did not clear the extra grant');
        $this->assertSame('volunteer', $staff->fresh()->role);
    }

    public function test_you_cannot_remove_your_own_settings_access(): void
    {
        $this->put("/settings/permissions/{$this->admin->id}", [
            'capabilities' => ['access_hub'],
        ])->assertSessionHasErrors('capabilities');

        $this->assertTrue($this->admin->fresh()->hasCapability('manage_settings'));
    }

    public function test_the_last_settings_holder_cannot_be_stripped(): void
    {
        // A second admin, so the first can be edited at all.
        $other = User::factory()->create(['role' => 'administrator']);

        // Remove settings from the original admin: allowed, $other still holds it.
        $this->actingAs($other);
        $this->put("/settings/permissions/{$this->admin->id}", [
            'capabilities' => ['access_hub'],
        ])->assertSessionHasNoErrors();

        // Now $other is the only holder and cannot strip themselves.
        $this->put("/settings/permissions/{$other->id}", [
            'capabilities' => ['access_hub'],
        ])->assertSessionHasErrors('capabilities');

        $this->assertTrue($other->fresh()->hasCapability('manage_settings'));
    }

    public function test_the_permissions_screen_is_itself_gated(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/settings/permissions')->assertForbidden();
        $this->actingAs($staff)->put("/settings/permissions/{$staff->id}", [
            'capabilities' => config('capabilities.all'),
        ])->assertForbidden();

        $this->assertFalse($staff->fresh()->hasCapability('manage_finance'), 'a user escalated their own access');
    }
}
