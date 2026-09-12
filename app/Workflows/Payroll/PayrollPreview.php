<?php

namespace App\Workflows\Payroll;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Support\PayrollRates;

/**
 * Read-only. Shows exactly how every figure in a pay period was arrived at,
 * and what would stop it being approved.
 *
 * Nothing here writes. It is safe to call on every page load, and it is the
 * same object the finalise gate uses — so the preview can never disagree with
 * what validation will decide.
 */
final class PayrollPreview
{
    public function __construct(private readonly PayrollPeriod $period) {}

    public static function for(PayrollPeriod $period): self
    {
        return new self($period);
    }

    /**
     * Per-entry working: the inputs, the arithmetic, and any problem with it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        return $this->period->entries()
            ->with('payable')
            ->orderBy('staff_name')
            ->get()
            ->map(function (PayrollEntry $entry) {
                $problems = $this->problemsForEntry($entry);

                $rate = (float) $entry->hourly_rate;
                $hours = (float) $entry->total_hours;
                $basic = round($rate * $hours, 2);

                return [
                    'id' => $entry->id,
                    'staff_name' => $entry->staff_name,
                    'linked' => $entry->payable !== null,
                    'hourly_rate' => $rate,
                    'total_hours' => $hours,
                    'basic_pay' => $basic,
                    'holiday_pay' => (float) $entry->holiday_pay,
                    'total_ssp' => (float) $entry->total_ssp,
                    'mileage' => (float) $entry->mileage,
                    'mileage_pay' => (float) $entry->mileage_pay,
                    'total' => round(
                        $basic + (float) $entry->holiday_pay + (float) $entry->total_ssp + (float) $entry->mileage_pay,
                        2,
                    ),
                    // Shown verbatim in the UI so the figure is never a black box.
                    'working' => sprintf(
                        '%s hrs × £%s = £%s basic, + £%s holiday + £%s SSP + £%s mileage',
                        rtrim(rtrim(number_format($hours, 2), '0'), '.'),
                        number_format($rate, 2),
                        number_format($basic, 2),
                        number_format((float) $entry->holiday_pay, 2),
                        number_format((float) $entry->total_ssp, 2),
                        number_format((float) $entry->mileage_pay, 2),
                    ),
                    'problems' => $problems,
                ];
            })
            ->all();
    }

    /**
     * Everything blocking approval, in plain English.
     *
     * @return array<int, string>
     */
    public function problems(): array
    {
        $problems = [];

        $entries = $this->period->entries()->with('payable')->get();

        if ($entries->isEmpty()) {
            $problems[] = 'This pay period has no entries.';
        }

        foreach ($entries as $entry) {
            foreach ($this->problemsForEntry($entry) as $problem) {
                $problems[] = $problem;
            }
        }

        // A name appearing twice usually means someone was added manually as well
        // as prefilled — paying them twice is silent and expensive.
        $duplicates = $entries->groupBy(fn (PayrollEntry $e) => mb_strtolower(trim($e->staff_name)))
            ->filter(fn ($group) => $group->count() > 1)
            ->keys();

        foreach ($duplicates as $name) {
            $problems[] = ucwords($name).' appears more than once in this pay period.';
        }

        return array_values(array_unique($problems));
    }

    /** @return array<int, string> */
    private function problemsForEntry(PayrollEntry $entry): array
    {
        $problems = [];
        $name = $entry->staff_name;

        // The original failure: no rate on record became £0.00 without comment.
        if ((float) $entry->hourly_rate <= 0) {
            $onRecord = $entry->payable
                ? PayrollRates::asAt($entry->payable, $this->period->end_date)
                : null;

            $problems[] = $onRecord === null
                ? "{$name} has no hourly rate configured for this pay period."
                : "{$name} has an hourly rate of £0.00 on this pay run, but £".number_format($onRecord, 2).' is on record.';
        }

        if ((float) $entry->total_hours <= 0 && (float) $entry->total_ssp <= 0 && (float) $entry->holiday_pay <= 0) {
            $problems[] = "{$name} has no hours, holiday or SSP recorded — remove the line or enter the hours worked.";
        }

        if ($entry->payable === null) {
            $problems[] = "{$name} isn't linked to a staff record, so this line won't post to staff costs in Finance.";
        }

        // Guards against a stray keypress turning 7.5 hours into 75.
        if ((float) $entry->total_hours > 200) {
            $problems[] = "{$name} has ".rtrim(rtrim(number_format((float) $entry->total_hours, 2), '0'), '.').' hours recorded for a 4-week period — please check this is correct.';
        }

        return $problems;
    }

    /** @return array<string, mixed> */
    public function totals(): array
    {
        $lines = $this->lines();

        return [
            'staff_count' => count($lines),
            'total_hours' => round(array_sum(array_column($lines, 'total_hours')), 2),
            'basic_pay' => round(array_sum(array_column($lines, 'basic_pay')), 2),
            'holiday_pay' => round(array_sum(array_column($lines, 'holiday_pay')), 2),
            'total_ssp' => round(array_sum(array_column($lines, 'total_ssp')), 2),
            'mileage_pay' => round(array_sum(array_column($lines, 'mileage_pay')), 2),
            'total' => round(array_sum(array_column($lines, 'total')), 2),
        ];
    }

    /** Everything the Payroll/Show page needs to render the preview. */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines(),
            'totals' => $this->totals(),
            'problems' => $this->problems(),
            'can_approve' => $this->problems() === [],
        ];
    }
}
