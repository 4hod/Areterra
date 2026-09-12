<?php

namespace App\Workflows\Payroll;

use App\Events\PayrollApproved;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Workflows\Workflow;

/**
 * Draft → Approved. The gate point 16 asks for: nothing sensitive jumps
 * straight from data entry to finished.
 *
 * Approval recomputes every total from the stored inputs rather than trusting
 * whatever the browser last posted, so an approved run always matches its own
 * arithmetic.
 */
final class ApprovePayrollPeriod extends Workflow
{
    public function __construct(
        private readonly PayrollPeriod $period,
        private readonly string $approvedBy,
    ) {}

    public function problems(): array
    {
        if ($this->period->isLocked()) {
            return ['This pay period has already been approved.'];
        }

        return PayrollPreview::for($this->period)->problems();
    }

    protected function summary(): string
    {
        return 'This pay run cannot be approved yet.';
    }

    protected function context(): array
    {
        return ['payroll_period_id' => $this->period->id];
    }

    protected function execute(): PayrollPeriod
    {
        $this->period->entries->each(function (PayrollEntry $entry) {
            $entry->forceFill(PayrollEntry::computeTotals([
                'hourly_rate' => $entry->hourly_rate,
                'total_hours' => $entry->total_hours,
                'holiday_pay' => $entry->holiday_pay,
                'total_ssp' => $entry->total_ssp,
                'mileage_pay' => $entry->mileage_pay,
            ]))->save();
        });

        $this->period->update(['authorised_by' => $this->approvedBy]);

        // Goes through the status machine so the move is recorded and illegal
        // jumps (draft -> paid) are impossible.
        $this->period->transitionTo('finalised', "Approved by {$this->approvedBy}");

        // Finance listens for this and posts staff costs — see point 4.
        PayrollApproved::dispatch($this->period->fresh('entries'));

        return $this->period;
    }
}
