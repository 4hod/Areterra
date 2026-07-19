<?php

namespace Tests\Feature;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\StaffRosterMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_compute_totals(): void
    {
        $totals = PayrollEntry::computeTotals([
            'hourly_rate' => 12.71,
            'total_hours' => 30,
            'holiday_pay' => 20,
            'total_ssp' => 0,
            'mileage_pay' => 4.50,
        ]);

        $this->assertSame(381.30, $totals['basic_pay']);
        $this->assertSame(405.80, $totals['total']);
    }

    public function test_new_period_prefills_active_roster_at_current_rate(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $lucy = StaffRosterMember::create(['name' => 'Lucy Mills', 'active' => true]);
        $lucy->rates()->create(['hourly_rate' => 12.71, 'effective_from' => today()->subMonth()]);
        StaffRosterMember::create(['name' => 'Old Staff', 'active' => false]);

        $this->actingAs($manager)->post('/payroll/periods', [
            'label' => 'July 2026',
            'start_date' => '2026-06-22',
            'end_date' => '2026-07-21',
            'pay_date' => '2026-07-28',
        ])->assertRedirect();

        $period = PayrollPeriod::first();
        $this->assertCount(1, $period->entries);
        $this->assertSame('Lucy Mills', $period->entries->first()->staff_name);
        $this->assertSame(12.71, (float) $period->entries->first()->hourly_rate);
    }

    public function test_totals_are_recomputed_server_side_on_save(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $period = PayrollPeriod::create(['label' => 'Test', 'start_date' => today(), 'end_date' => today()]);

        $this->actingAs($manager)->put("/payroll/periods/{$period->id}/entries", [
            'entries' => [[
                'id' => null,
                'staff_name' => 'Lucy Mills',
                'hourly_rate' => 12.71,
                'total_hours' => 30,
                'holiday_pay' => 20,
                'mileage' => 10,
                'mileage_pay' => 4.50,
            ]],
        ])->assertRedirect();

        $entry = $period->entries()->first();
        $this->assertSame(381.30, (float) $entry->basic_pay);
        $this->assertSame(405.80, (float) $entry->total);
    }

    public function test_finalised_period_rejects_edits(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $period = PayrollPeriod::create([
            'label' => 'Test', 'start_date' => today(), 'end_date' => today(), 'status' => 'finalised',
        ]);

        $this->actingAs($manager)->put("/payroll/periods/{$period->id}/entries", [
            'entries' => [['id' => null, 'staff_name' => 'X']],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $period->entries()->count());
    }

    public function test_rate_change_preserves_history(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $lucy = StaffRosterMember::create(['name' => 'Lucy Mills', 'active' => true]);
        $lucy->rates()->create(['hourly_rate' => 12.71, 'effective_from' => today()->subYear()]);

        $this->actingAs($manager)->put("/payroll/roster/{$lucy->id}", ['hourly_rate' => 13.50])
            ->assertRedirect();

        $this->assertSame(2, $lucy->rates()->count());
        $this->assertSame(13.50, $lucy->fresh()->currentRate());
    }

    public function test_staff_cannot_access_payroll(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/payroll')->assertForbidden();
    }
}
