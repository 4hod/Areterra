<?php

namespace Tests\Feature;

use App\Support\Period;
use Tests\TestCase;

class PeriodEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['periods.anchor' => '2026-01-05']);
    }

    public function test_resolves_periods_either_side_of_the_anchor(): void
    {
        $this->assertSame(0, Period::containing('2026-01-05')->index);
        $this->assertSame(0, Period::containing('2026-02-01')->index);
        $this->assertSame(1, Period::containing('2026-02-02')->index);
        $this->assertSame(-1, Period::containing('2025-12-31')->index);
    }

    public function test_has_no_gaps_or_overlaps(): void
    {
        for ($i = -20; $i < 20; $i++) {
            $this->assertTrue(
                Period::atIndex($i)->end->addDay()->isSameDay(Period::atIndex($i + 1)->start),
                "Gap or overlap between period {$i} and ".($i + 1),
            );
        }
    }

    public function test_every_frequency_round_trips(): void
    {
        foreach (['weekly', 'fortnightly', 'four_weekly', 'monthly', 'quarterly', 'annually'] as $frequency) {
            $this->assertEqualsWithDelta(
                100.0,
                Period::fromFourWeekly(Period::toFourWeekly(100.0, $frequency), $frequency),
                0.0001,
                "Frequency [{$frequency}] did not round-trip.",
            );
        }
    }

    public function test_thirteen_four_week_periods_make_a_year(): void
    {
        $this->assertEqualsWithDelta(1300.0, Period::toFourWeekly(1300.0, 'annually') * 13, 0.01);
    }

    public function test_counts_operating_days_in_a_period(): void
    {
        // Areterra runs Mon, Tue, Thu, Fri — four days across four weeks.
        $this->assertSame(16, Period::atIndex(0)->operatingDays());
        $this->assertSame(4, Period::atIndex(0)->operatingDaysFor(2));
        $this->assertSame(0, Period::atIndex(0)->operatingDaysFor(3));
    }
}
