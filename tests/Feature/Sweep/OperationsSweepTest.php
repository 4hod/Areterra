<?php

namespace Tests\Feature\Sweep;

use App\Models\Task;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class OperationsSweepTest extends SweepTestCase
{
    public function test_register_check_in_update_absent_and_cancel_day(): void
    {
        $m = $this->member();
        $this->get('/register')->assertOk();

        $this->assertWriteOk($this->post("/register/{$m->id}/check-in", [
            'arrival_mood' => 'happy', 'notes' => 'Arrived on the minibus.',
        ]), 'register.check-in');
        $att = DB::table('attendances')->where('member_id', $m->id)->first();
        $this->assertNotNull($att, 'attendance not created on check-in');
        $this->assertEquals(1, $att->checked_in, 'checked_in flag not set');

        $this->assertWriteOk($this->put("/register/{$m->id}", [
            'arrival_mood' => 'neutral', 'notes' => 'Settled after a wobble.',
        ]), 'register.update');
        $this->assertSame('neutral', DB::table('attendances')->where('member_id', $m->id)->first()->arrival_mood);

        $m2 = $this->member();
        $this->assertWriteOk($this->post("/register/{$m2->id}/absent", [
            'absence_reason' => 'Unwell',
        ]), 'register.absent');
        $this->assertSame('absent', DB::table('attendances')->where('member_id', $m2->id)->first()->status);

        $this->assertWriteOk($this->post('/register/cancel-day', [
            'date' => now()->toDateString(), 'reason' => 'Snow — site closed',
        ]), 'register.cancel-day');
        $this->assertNotNull(DB::table('day_cancellations')->first(), 'day cancellation not recorded');
    }

    public function test_transport_complete_outcome_undo_and_payments(): void
    {
        $m = $this->scheduledMember();
        $this->get('/transport')->assertOk();

        $this->assertWriteOk($this->post("/transport/{$m->id}/complete", ['phase' => 'morning']), 'transport.complete');
        $run = DB::table('transport_runs')->where('member_id', $m->id)->first();
        $this->assertNotNull($run, 'transport run not created');
        $this->assertNotNull($run->completed_at, 'run not marked complete');

        $charge = DB::table('transport_ledger')->where('member_id', $m->id)->sum('amount');
        $this->assertNotEquals(0.0, (float) $charge, 'completing a leg raised no charge');

        // Undo should reverse the charge, not just delete the run.
        $this->assertWriteOk($this->post("/transport/{$m->id}/undo", ['phase' => 'morning']), 'transport.undo');
        $after = (float) DB::table('transport_ledger')->where('member_id', $m->id)->sum('amount');
        $this->assertSame(0.0, $after, 'undo left the charge standing (member still billed)');

        // Outcome path
        $this->assertWriteOk($this->post("/transport/{$m->id}/outcome", [
            'phase' => 'morning', 'outcome' => 'collected',
        ]), 'transport.outcome');

        // Payment + deletion
        $this->assertWriteOk($this->post("/transport/{$m->id}/pay", [
            'amount' => 10.00, 'entry_date' => now()->toDateString(), 'notes' => 'Cash from mum',
        ]), 'transport.pay');
        $pay = DB::table('transport_ledger')->where('member_id', $m->id)->where('amount', '>', 0)->first();
        $this->assertNotNull($pay, 'payment not recorded');

        $this->assertWriteOk($this->delete("/transport/payments/{$pay->id}"), 'transport.payments.destroy');
        $this->assertGone('transport_ledger', $pay->id, 'transport.payments.destroy');

        $this->get("/transport/{$m->id}/statement")->assertOk();
    }

    public function test_end_of_day_record(): void
    {
        $m = $this->member();
        $this->post("/register/{$m->id}/check-in", ['arrival_mood' => 'happy']);
        $this->get('/end-of-day')->assertOk();

        $this->assertWriteOk($this->post("/end-of-day/{$m->id}", [
            'end_mood' => 'happy', 'session_type' => 'Farm morning',
            'activities' => 'Mucking out, feeding',
            'food_intake' => 'good', 'fluid_intake' => 'good',
            'notes' => 'Great day.',
        ]), 'end-of-day.store');
        $this->assertNotNull(DB::table('end_of_day_records')->where('member_id', $m->id)->first(), 'end of day record not created');
    }

    public function test_tasks_create_complete_reopen_and_update(): void
    {
        $this->get('/tasks')->assertOk();

        $this->assertWriteOk($this->post('/tasks', [
            'title' => 'Order more bedding', 'priority' => 'medium',
            'due_date' => now()->addWeek()->toDateString(),
            'assigned_to' => $this->admin->id,
        ]), 'tasks.store');
        $t = Task::where('title', 'Order more bedding')->first();
        $this->assertNotNull($t, 'task not created');

        $this->assertWriteOk($this->post("/tasks/{$t->id}/complete"), 'tasks.complete');
        $this->assertNotNull($t->fresh()->completed_at, 'task not completed');

        $this->assertWriteOk($this->post("/tasks/{$t->id}/reopen"), 'tasks.reopen');
        $this->assertNull($t->fresh()->completed_at, 'task not reopened');

        $this->assertWriteOk($this->put("/tasks/{$t->id}", [
            'priority' => 'high', 'assigned_to' => $this->admin->id,
        ]), 'tasks.update');
        $this->assertSame('high', $t->fresh()->priority);
    }

    public function test_maintenance_tasks_full_lifecycle(): void
    {
        $this->get('/maintenance')->assertOk();

        $this->assertWriteOk($this->post('/maintenance', [
            'title' => 'Fix the barn gate', 'priority' => 'high',
            'due_date' => now()->addDays(3)->toDateString(),
        ]), 'maintenance.store');
        $t = DB::table('maintenance_tasks')->where('title', 'Fix the barn gate')->first();
        $this->assertNotNull($t, 'maintenance task not created');

        $this->assertWriteOk($this->put("/maintenance/{$t->id}", [
            'title' => 'Fix the barn gate', 'priority' => 'medium', 'notes' => 'Parts ordered',
        ]), 'maintenance.update');
        $this->assertSame('medium', DB::table('maintenance_tasks')->find($t->id)->priority);

        $this->assertWriteOk($this->post("/maintenance/{$t->id}/complete"), 'maintenance.complete');

        $this->assertWriteOk($this->delete("/maintenance/{$t->id}"), 'maintenance.destroy');
        $this->assertGone('maintenance_tasks', $t->id, 'maintenance.destroy');
    }

    public function test_incidents_create_and_status_update(): void
    {
        $this->get('/incidents')->assertOk();
        $v = Vehicle::create(['registration' => 'XY99ZZZ', 'active' => true]);

        $this->assertWriteOk($this->post('/incidents', [
            'title' => 'Minibus clipped a post',
            'occurred_at' => now()->toDateTimeString(),
            'location' => 'Car park',
            'description' => 'Reversing into the bay.',
            'severity' => 'minor',
            'subject_type' => Vehicle::class,
            'subject_id' => $v->id,
        ]), 'incidents.store');
        $i = DB::table('incidents')->where('title', 'Minibus clipped a post')->first();
        $this->assertNotNull($i, 'incident not created');

        $this->assertWriteOk($this->put("/incidents/{$i->id}", [
            'status' => 'closed', 'actions_taken' => 'Panel repaired.',
        ]), 'incidents.update');
        $this->assertSame('closed', DB::table('incidents')->find($i->id)->status);
    }

    public function test_compliance_items_create_and_complete(): void
    {
        $this->get('/compliance')->assertOk();

        $this->assertWriteOk($this->post('/compliance', [
            'title' => 'Fire extinguisher service',
            'category' => 'Premises',
            'due_date' => now()->addMonth()->toDateString(),
        ]), 'compliance.store');
        $c = DB::table('compliance_items')->where('title', 'Fire extinguisher service')->first();
        $this->assertNotNull($c, 'compliance item not created');

        $this->assertWriteOk($this->post("/compliance/{$c->id}/complete"), 'compliance.complete');
        $this->assertNotNull(DB::table('compliance_items')->find($c->id)->completed_at, 'compliance item not completed');
    }

    public function test_product_orders_full_approval_chain(): void
    {
        $this->get('/orders')->assertOk();

        $this->assertWriteOk($this->post('/orders', [
            'item_name' => 'Chinchilla dust', 'quantity' => 4, 'unit' => 'bags',
            'category' => 'Animal care',
        ]), 'orders.store');
        $o = DB::table('product_orders')->where('item_name', 'Chinchilla dust')->first();
        $this->assertNotNull($o, 'order not created');
        $this->assertSame('pending', $o->status);

        $this->assertWriteOk($this->put("/orders/{$o->id}/approve"), 'orders.approve');
        $this->assertSame('approved', DB::table('product_orders')->find($o->id)->status);

        $this->assertWriteOk($this->put("/orders/{$o->id}/ordered", [
            'supplier' => 'Pets Ltd', 'cost' => 24.50,
            'expected_delivery_date' => now()->addWeek()->toDateString(),
        ]), 'orders.ordered');
        $this->assertSame('ordered', DB::table('product_orders')->find($o->id)->status);

        $this->assertWriteOk($this->put("/orders/{$o->id}/delivered", [
            'delivery_notes' => 'Left in the feed store.',
        ]), 'orders.delivered');
        $this->assertSame('delivered', DB::table('product_orders')->find($o->id)->status);

        // Reject + cancel on a second order
        $this->post('/orders', ['item_name' => 'Gold-plated hutch', 'quantity' => 1]);
        $o2 = DB::table('product_orders')->where('item_name', 'Gold-plated hutch')->first();
        $this->assertWriteOk($this->put("/orders/{$o2->id}/reject", [
            'rejection_reason' => 'Out of budget',
        ]), 'orders.reject');
        $this->assertSame('rejected', DB::table('product_orders')->find($o2->id)->status);

        $this->post('/orders', ['item_name' => 'Straw bales', 'quantity' => 10]);
        $o3 = DB::table('product_orders')->where('item_name', 'Straw bales')->first();
        $this->assertWriteOk($this->delete("/orders/{$o3->id}"), 'orders.cancel');
        $this->assertGone('product_orders', $o3->id, 'orders.cancel');
    }
}
