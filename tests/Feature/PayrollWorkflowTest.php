<?php

namespace Tests\Feature;

use App\Exceptions\WorkflowException;
use App\Models\Grant;
use App\Models\GrantExpenditure;
use App\Models\LedgerEntry;
use App\Models\PayrollRate;
use App\Models\StaffRosterMember;
use App\Support\Ledger;
use App\Support\Period;
use App\Workflows\Payroll\ApprovePayrollPeriod;
use App\Workflows\Payroll\OpenPayrollPeriod;
use App\Workflows\Payroll\PayrollPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 7 — real Areterra workflows, not "does the page load".
 */
class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function openPeriod(array $overrides = []): \App\Models\PayrollPeriod
    {
        return (new OpenPayrollPeriod([
            'label' => 'Test period',
            'start_date' => '2026-01-05',
            'end_date' => '2026-02-01',
            ...$overrides,
        ]))->run();
    }

    public function test_missing_rate_names_the_person_instead_of_paying_zero(): void
    {
        StaffRosterMember::create(['name' => 'Vanessa Example', 'active' => true]);

        $workflow = new OpenPayrollPeriod([
            'label' => 'Test period',
            'start_date' => '2026-01-05',
            'end_date' => '2026-02-01',
        ]);
        $period = $workflow->run();

        $this->assertNotEmpty($workflow->warnings);
        $this->assertStringContainsString('Vanessa Example', $workflow->warnings[0]);

        $this->assertContains(
            'Vanessa Example has no hourly rate configured for this pay period.',
            PayrollPreview::for($period)->problems(),
        );
    }

    public function test_approval_is_refused_when_a_rate_is_missing(): void
    {
        StaffRosterMember::create(['name' => 'Vanessa Example', 'active' => true]);
        $period = $this->openPeriod();

        $this->expectException(WorkflowException::class);

        try {
            (new ApprovePayrollPeriod($period, 'Bob'))->run();
        } finally {
            $this->assertSame('draft', $period->fresh()->status);
        }
    }

    public function test_uses_the_rate_in_force_during_the_period_not_todays(): void
    {
        $staff = StaffRosterMember::create(['name' => 'Lucy Example', 'active' => true]);

        PayrollRate::create([
            'payable_type' => StaffRosterMember::class, 'payable_id' => $staff->id,
            'hourly_rate' => 11.00, 'effective_from' => '2025-01-01',
        ]);
        PayrollRate::create([
            'payable_type' => StaffRosterMember::class, 'payable_id' => $staff->id,
            'hourly_rate' => 13.50, 'effective_from' => '2026-06-01',
        ]);

        $period = $this->openPeriod();

        // The pay rise came after this period — it must not be applied to it.
        $this->assertSame(11.00, (float) $period->entries()->first()->hourly_rate);
    }

    public function test_approved_payroll_posts_to_the_ledger_exactly_once(): void
    {
        $staff = StaffRosterMember::create(['name' => 'Lucy Example', 'active' => true]);
        PayrollRate::create([
            'payable_type' => StaffRosterMember::class, 'payable_id' => $staff->id,
            'hourly_rate' => 12.00, 'effective_from' => '2025-01-01',
        ]);

        $period = $this->openPeriod(['pay_date' => '2026-02-05']);
        $period->entries()->first()->update(['total_hours' => 100]);

        (new ApprovePayrollPeriod($period->fresh(), 'Bob'))->run();

        $this->assertSame(1, LedgerEntry::where('category', 'staff_costs')->count());
        $this->assertSame(1200.00, (float) LedgerEntry::where('category', 'staff_costs')->first()->amount);
    }

    public function test_restricted_grant_spend_does_not_inflate_unrestricted_reserves(): void
    {
        $grant = Grant::create([
            'title' => 'Lottery', 'funder' => 'National Lottery',
            'amount' => 5000, 'status' => 'active',
        ]);

        $expenditure = GrantExpenditure::create([
            'grant_id' => $grant->id, 'description' => 'Equipment',
            'amount' => 500, 'spent_date' => '2026-01-10',
        ]);

        Ledger::post($expenditure, 'expense', 'grant_spend', 'Equipment', 500, '2026-01-10', $grant);

        $summary = Ledger::summaryFor(Period::containing('2026-01-10'));

        $this->assertSame(500.00, $summary['restricted_spend']);
        $this->assertSame(0.00, $summary['unrestricted_net']);
    }

    public function test_a_ledger_entry_is_reversed_not_destroyed(): void
    {
        $grant = Grant::create(['title' => 'Lottery', 'funder' => 'NL', 'amount' => 5000, 'status' => 'active']);
        $expenditure = GrantExpenditure::create([
            'grant_id' => $grant->id, 'description' => 'Mistake',
            'amount' => 200, 'spent_date' => '2026-01-10',
        ]);

        $entry = Ledger::post($expenditure, 'expense', 'grant_spend', 'Mistake', 200, '2026-01-10', $grant);
        Ledger::reverse($entry, 'Entered against the wrong grant');

        $this->assertNotNull(LedgerEntry::find($entry->id), 'The original entry must survive.');
        $this->assertSame(0, LedgerEntry::effective()->count());
    }
}
