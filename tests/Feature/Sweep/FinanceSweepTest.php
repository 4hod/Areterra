<?php

namespace Tests\Feature\Sweep;

use Illuminate\Support\Facades\DB;

class FinanceSweepTest extends SweepTestCase
{
    public function test_grants_create_update_and_record_expenditure(): void
    {
        $this->get('/finance')->assertOk();

        $this->assertWriteOk($this->post('/finance/grants', [
            'title' => 'Community Fund 2026', 'funder' => 'Big Lottery',
            'amount' => 15000, 'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]), 'grants.store');
        $g = DB::table('grants')->where('title', 'Community Fund 2026')->first();
        $this->assertNotNull($g, 'grant not created');

        $this->assertWriteOk($this->put("/finance/grants/{$g->id}", [
            'status' => 'completed', 'notes' => 'Final report filed.',
        ]), 'grants.update');
        $this->assertSame('completed', DB::table('grants')->find($g->id)->status);

        $this->assertWriteOk($this->post("/finance/grants/{$g->id}/expenditures", [
            'description' => 'Feed and bedding', 'amount' => 320.40,
            'spent_date' => now()->toDateString(),
        ]), 'grants.spend');
        $this->assertNotNull(DB::table('grant_expenditures')->where('grant_id', $g->id)->first(), 'expenditure not created');

        // The expenditure should have posted to the ledger as restricted spend.
        $this->assertNotNull(
            DB::table('ledger_entries')->where('category', 'grant_spend')->first(),
            'grant expenditure did not post to the ledger'
        );
    }

    public function test_rates_and_member_finance_profile(): void
    {
        $this->assertWriteOk($this->put('/finance/rates', [
            'full_day' => 85, 'half_day' => 45,
            'one_to_one_hourly' => 22.5, 'transport_day' => 5,
        ]), 'finance.rates');

        $m = $this->member();
        $this->assertWriteOk($this->put("/finance/members/{$m->id}", [
            'attendance_type' => 'full_day',
            'one_to_one_hours_per_week' => 2,
            'charge_transport' => true,
            'funding_source' => 'local_authority',
        ]), 'finance.members.update');
        $this->assertNotNull(
            DB::table('member_finance_profiles')->where('member_id', $m->id)->first(),
            'member finance profile not created'
        );
    }

    public function test_additional_income_and_fixed_costs_full_lifecycle(): void
    {
        $this->assertWriteOk($this->post('/finance/additional-income', [
            'description' => 'Summer fete', 'amount' => 1200,
            'frequency' => 'one_off', 'active' => true,
            'start_date' => now()->toDateString(),
        ]), 'finance.additional.store');
        $i = DB::table('additional_incomes')->where('description', 'Summer fete')->first();
        $this->assertNotNull($i, 'additional income not created');

        $this->assertWriteOk($this->put("/finance/additional-income/{$i->id}", [
            'description' => 'Summer fete', 'amount' => 1350,
            'frequency' => 'one_off', 'active' => false,
        ]), 'finance.additional.update');
        $this->assertEquals(1350, DB::table('additional_incomes')->find($i->id)->amount);

        $this->assertWriteOk($this->delete("/finance/additional-income/{$i->id}"), 'finance.additional.destroy');
        $this->assertGone('additional_incomes', $i->id, 'finance.additional.destroy');

        $this->assertWriteOk($this->post('/finance/fixed-costs', [
            'description' => 'Insurance', 'amount' => 240,
            'frequency' => 'monthly', 'active' => true,
        ]), 'finance.costs.store');
        $c = DB::table('fixed_costs')->where('description', 'Insurance')->first();
        $this->assertNotNull($c, 'fixed cost not created');

        $this->assertWriteOk($this->put("/finance/fixed-costs/{$c->id}", [
            'description' => 'Insurance', 'amount' => 260,
            'frequency' => 'monthly', 'active' => true,
        ]), 'finance.costs.update');
        $this->assertEquals(260, DB::table('fixed_costs')->find($c->id)->amount);

        $this->assertWriteOk($this->delete("/finance/fixed-costs/{$c->id}"), 'finance.costs.destroy');
        $this->assertGone('fixed_costs', $c->id, 'finance.costs.destroy');
    }

    public function test_invoices_create_update_pay_bulk_and_delete(): void
    {
        $this->get('/invoices')->assertOk();
        $m = $this->member();

        $this->assertWriteOk($this->post('/invoices', [
            'member_id' => $m->id, 'qb_reference' => 'INV-1001',
            'amount' => 425.00, 'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), 'status' => 'sent',
        ]), 'invoices.store');
        $inv = DB::table('member_invoices')->where('qb_reference', 'INV-1001')->first();
        $this->assertNotNull($inv, 'invoice not created');

        $this->assertWriteOk($this->put("/invoices/{$inv->id}", [
            'status' => 'overdue',
        ]), 'invoices.update');
        $this->assertSame('overdue', DB::table('member_invoices')->find($inv->id)->status);

        $this->assertWriteOk($this->post("/invoices/{$inv->id}/paid"), 'invoices.paid');
        $this->assertSame('paid', DB::table('member_invoices')->find($inv->id)->status);
        $this->assertNotNull(
            DB::table('ledger_entries')->where('category', 'member_fees')->first(),
            'paid invoice did not post income to the ledger'
        );

        // Bulk route must resolve before /invoices/{invoice}/paid.
        $this->post('/invoices', [
            'member_id' => $m->id, 'qb_reference' => 'INV-1002', 'amount' => 100,
            'invoice_date' => now()->toDateString(), 'status' => 'sent',
        ]);
        $inv2 = DB::table('member_invoices')->where('qb_reference', 'INV-1002')->first();
        $this->assertWriteOk($this->post('/invoices/bulk/paid', ['ids' => [$inv2->id]]), 'invoices.bulk-paid');
        $this->assertSame('paid', DB::table('member_invoices')->find($inv2->id)->status);

        $this->assertWriteOk($this->delete("/invoices/{$inv2->id}"), 'invoices.destroy');
        $this->assertGone('member_invoices', $inv2->id, 'invoices.destroy');
    }

    public function test_payroll_period_entries_approval_and_deletion(): void
    {
        $this->get('/payroll')->assertOk();

        // Roster member
        $this->assertWriteOk($this->post('/payroll/roster', [
            'name' => 'Jo Carer', 'job_title' => 'Support Worker',
            'email' => 'jo@example.test', 'hourly_rate' => 12.60,
        ]), 'payroll.roster.store');
        $r = DB::table('staff_roster')->where('name', 'Jo Carer')->first();
        $this->assertNotNull($r, 'roster member not created');

        $this->assertWriteOk($this->put("/payroll/roster/{$r->id}", [
            'name' => 'Jo Carer', 'job_title' => 'Senior Support Worker',
        ]), 'payroll.roster.update');
        $this->assertSame('Senior Support Worker', DB::table('staff_roster')->find($r->id)->job_title);

        // Rate
        $this->assertWriteOk($this->post('/payroll/rates', [
            'key' => "roster:{$r->id}", 'hourly_rate' => 13.20,
            'contracted_hours' => 30, 'effective_from' => now()->toDateString(),
        ]), 'payroll.rates.set');

        // Period
        $this->assertWriteOk($this->post('/payroll/periods', [
            'label' => 'September 2026', 'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'pay_date' => now()->endOfMonth()->toDateString(),
        ]), 'payroll.periods.store');
        $p = DB::table('payroll_periods')->where('label', 'September 2026')->first();
        $this->assertNotNull($p, 'payroll period not created');

        $this->get("/payroll/periods/{$p->id}")->assertOk();
        $this->get("/payroll/periods/{$p->id}/print")->assertOk();

        $this->assertWriteOk($this->put("/payroll/periods/{$p->id}", [
            'label' => 'September 2026', 'authorised_by' => 'Trustee Board',
        ]), 'payroll.periods.update');

        // Creating the period prefills a line per active payee, linked to their
        // staff record and carrying the rate on file. Editing fills in hours.
        $prefilled = DB::table('payroll_entries')->where('payroll_period_id', $p->id)->get();
        $this->assertNotEmpty($prefilled, 'period did not prefill entries from the roster');
        $this->assertNotNull($prefilled->first()->payable_id, 'prefilled entry not linked to a staff record');

        $this->assertWriteOk($this->put("/payroll/periods/{$p->id}/entries", [
            'entries' => $prefilled->map(fn ($e) => [
                'id' => $e->id,
                'staff_name' => $e->staff_name,
                'hourly_rate' => $e->hourly_rate ?: 13.20,
                'total_hours' => 120,
            ])->all(),
        ]), 'payroll.entries.save');

        $saved = DB::table('payroll_entries')->where('payroll_period_id', $p->id)->first();
        $this->assertGreaterThan(0, (float) $saved->total, 'payroll totals not recomputed on save');

        $this->assertWriteOk($this->post("/payroll/periods/{$p->id}/approve", [
            'approved_by' => 'Trustee Board',
        ]), 'payroll.approve');
        $this->assertNotNull(
            DB::table('ledger_entries')->where('category', 'staff_costs')->first(),
            'approved payroll did not post staff costs to the ledger'
        );

        $this->assertWriteOk($this->delete("/payroll/roster/{$r->id}"), 'payroll.roster.destroy');
        $this->assertGone('staff_roster', $r->id, 'payroll.roster.destroy');

        $this->assertWriteOk($this->delete("/payroll/periods/{$p->id}"), 'payroll.periods.destroy');
        $this->assertGone('payroll_periods', $p->id, 'payroll.periods.destroy');
    }
}
