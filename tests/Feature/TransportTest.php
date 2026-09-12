<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\TransportLedgerEntry;
use App\Models\TransportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'staff']);
        $this->member = Member::create(['first_name' => 'Amy', 'last_name' => 'Buckle']);
        $this->member->// Scheduled for whatever day the suite happens to run on — hardcoding
        // weekdays makes these tests fail every Saturday.
        settings()->create(['transport_required' => true, 'attendance_days' => [today()->isoWeekday()]]);
    }

    public function test_morning_collection_charges_one_leg_once(): void
    {
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);
        // Completing again (idempotent) must not double-charge.
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);

        $charges = TransportLedgerEntry::where('member_id', $this->member->id)->where('type', 'charge')->get();
        $this->assertCount(1, $charges);
        // Per leg now: £2.50 out, £2.50 back. A morning-only day is half a return.
        $this->assertSame(-2.5, TransportLedgerEntry::balanceFor($this->member->id));
    }

    public function test_afternoon_drop_off_charges_its_own_leg(): void
    {
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'afternoon']);

        // Each leg stands on its own now — a drop-off home is £2.50 whether or
        // not they were collected that morning.
        $this->assertSame(-2.5, TransportLedgerEntry::balanceFor($this->member->id));
    }

    public function test_undo_morning_removes_run_and_charge(): void
    {
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/undo", ['phase' => 'morning']);

        $this->assertSame(0, TransportRun::count());
        $this->assertSame(0.0, TransportLedgerEntry::balanceFor($this->member->id));
    }

    public function test_ten_pound_payment_gives_two_days_credit(): void
    {
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/pay", ['amount' => 10]);

        $this->assertSame(10.0, TransportLedgerEntry::balanceFor($this->member->id));

        // A collected morning leg costs £2.50, leaving £7.50.
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);
        $this->assertSame(7.5, TransportLedgerEntry::balanceFor($this->member->id));
    }
}
