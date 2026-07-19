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
        $this->member->settings()->create(['transport_required' => true]);
    }

    public function test_morning_collection_charges_five_pounds_once(): void
    {
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);
        // Completing again (idempotent) must not double-charge.
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);

        $charges = TransportLedgerEntry::where('member_id', $this->member->id)->where('type', 'charge')->get();
        $this->assertCount(1, $charges);
        $this->assertSame(-5.0, TransportLedgerEntry::balanceFor($this->member->id));
    }

    public function test_afternoon_drop_off_does_not_charge(): void
    {
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'afternoon']);

        $this->assertSame(0.0, TransportLedgerEntry::balanceFor($this->member->id));
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

        // First transport day auto-deducts £5, leaving one day in credit.
        $this->actingAs($this->user)->post("/transport/{$this->member->id}/complete", ['phase' => 'morning']);
        $this->assertSame(5.0, TransportLedgerEntry::balanceFor($this->member->id));
    }
}
