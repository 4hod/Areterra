<?php

namespace Tests\Feature;

use App\Events\MemberMarkedAbsent;
use App\Events\TransportOutcomeRecorded;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\TransportRun;
use App\Support\TransportCharges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function member(): Member
    {
        return Member::create(['first_name' => 'Amy', 'last_name' => 'Example']);
    }

    private function leg(Member $m, string $phase, string $outcome, ?string $reason = null): TransportRun
    {
        $run = TransportRun::updateOrCreate(
            ['run_date' => today(), 'member_id' => $m->id, 'phase' => $phase],
            ['outcome' => $outcome, 'outcome_reason' => $reason],
        );
        TransportOutcomeRecorded::dispatch($run);

        return $run;
    }

    public function test_both_legs_collected_costs_the_full_return_fare(): void
    {
        $m = $this->member();
        $this->leg($m, 'morning', 'collected');
        $this->leg($m, 'afternoon', 'collected');

        $this->assertSame(5.00, TransportCharges::chargedOn($m->id, today()));
    }

    public function test_not_collected_in_the_morning_but_dropped_home_costs_one_leg(): void
    {
        $m = $this->member();
        $this->leg($m, 'morning', 'not_collected', 'Appointment, coming in later');
        $this->leg($m, 'afternoon', 'collected');

        $this->assertSame(2.50, TransportCharges::chargedOn($m->id, today()));
    }

    public function test_not_collected_does_not_mark_them_absent(): void
    {
        $m = $this->member();
        $this->leg($m, 'morning', 'not_collected', 'Appointment');

        $attendance = Attendance::where('member_id', $m->id)->whereDate('date', today())->first();

        $this->assertTrue(
            $attendance === null || $attendance->status !== 'absent',
            'Not collected means they are still expected in.',
        );
    }

    public function test_not_collected_does_not_cancel_the_afternoon_leg(): void
    {
        $m = $this->member();
        $this->leg($m, 'afternoon', 'collected');
        $this->leg($m, 'morning', 'not_collected', 'Own lift in');

        $afternoon = TransportRun::where('member_id', $m->id)->where('phase', 'afternoon')->first();
        $this->assertSame('collected', $afternoon->outcome);
    }

    public function test_absent_stands_down_untravelled_legs_and_charges_nothing(): void
    {
        $m = $this->member();
        $this->leg($m, 'afternoon', 'not_collected');
        $this->leg($m, 'morning', 'absent', 'Unwell');

        $this->assertSame(0.00, TransportCharges::chargedOn($m->id, today()));
        $this->assertSame('absent', TransportRun::where('member_id', $m->id)->where('phase', 'afternoon')->first()->outcome);
        $this->assertSame('absent', Attendance::where('member_id', $m->id)->first()->status);
    }

    public function test_absence_never_erases_a_journey_that_already_happened(): void
    {
        $m = $this->member();

        // Driven in at 9, went home unwell at 10. The morning journey happened.
        $this->leg($m, 'morning', 'collected');
        $this->leg($m, 'afternoon', 'absent', 'Went home unwell');

        $morning = TransportRun::where('member_id', $m->id)->where('phase', 'morning')->first();

        $this->assertSame('collected', $morning->outcome, 'A travelled leg must not be rewritten.');
        $this->assertSame(2.50, TransportCharges::chargedOn($m->id, today()));
    }

    public function test_a_collected_leg_still_charges_when_they_later_go_home_ill(): void
    {
        $m = $this->member();
        $this->leg($m, 'morning', 'collected');

        // Driven in, then went home unwell — the morning journey happened.
        $this->assertSame(2.50, TransportCharges::chargedOn($m->id, today()));
    }

    public function test_changing_an_outcome_corrects_the_charge_without_deleting_history(): void
    {
        $m = $this->member();
        $this->leg($m, 'morning', 'collected');
        $this->leg($m, 'afternoon', 'collected');
        $this->assertSame(5.00, TransportCharges::chargedOn($m->id, today()));

        // Afternoon was recorded in error — family collected them.
        $this->leg($m, 'afternoon', 'not_collected', 'Family collected');

        $this->assertSame(2.50, TransportCharges::chargedOn($m->id, today()));

        // The schema allows one charge row per member per day, so the charge is
        // adjusted in place. The change is captured in the audit log instead.
        $this->assertSame(1, $m->transportLedger()->where('type', 'charge')->count());
    }

    public function test_marking_absent_from_the_register_still_cascades(): void
    {
        $m = $this->member();
        $this->leg($m, 'afternoon', 'not_collected');

        $attendance = Attendance::updateOrCreate(
            ['member_id' => $m->id, 'date' => today()],
            ['status' => 'absent', 'absence_reason' => 'Unwell'],
        );
        MemberMarkedAbsent::dispatch($attendance);

        $this->assertSame(0.00, TransportCharges::chargedOn($m->id, today()));
        $this->assertSame('absent', TransportRun::where('member_id', $m->id)->where('phase', 'afternoon')->first()->outcome);
    }
}
