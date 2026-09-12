<?php

namespace App\Workflows\Payroll;

use App\Models\PayrollPeriod;
use App\Models\StaffRosterMember;
use App\Models\User;
use App\Support\PayrollRates;
use App\Workflows\Workflow;
use Illuminate\Support\Collection;

/**
 * Opens a pay period and prefills a line per payee.
 *
 * Two behaviours changed from the original storePeriod():
 *
 *  1. Users with accounts are prefilled as well as roster members. The index
 *     screen listed both, but only roster members were ever given entries, so
 *     staff with logins silently vanished from the pay run.
 *  2. A missing rate is no longer written as 0.00. The line is still created —
 *     you need to see the person — but it is reported back by name, and the
 *     preview blocks approval until it is set.
 */
final class OpenPayrollPeriod extends Workflow
{
    private ?Collection $payees = null;

    /** @var array<int, string> */
    public array $warnings = [];

    public function __construct(private readonly array $attributes) {}

    public function problems(): array
    {
        $problems = [];

        if ($this->payees()->isEmpty()) {
            $problems[] = 'There are no active staff to pay. Add someone to the roster first.';
        }

        $overlapping = PayrollPeriod::query()
            ->whereDate('start_date', '<=', $this->attributes['end_date'])
            ->whereDate('end_date', '>=', $this->attributes['start_date'])
            ->first();

        if ($overlapping) {
            $problems[] = "These dates overlap an existing pay period ({$overlapping->label}).";
        }

        return $problems;
    }

    protected function summary(): string
    {
        return 'The pay period could not be created.';
    }

    protected function execute(): PayrollPeriod
    {
        $period = PayrollPeriod::create($this->attributes);

        foreach ($this->payees() as $payee) {
            $rate = PayrollRates::asAt($payee, $period->end_date);

            if ($rate === null) {
                $this->warnings[] = "{$payee->name} has no hourly rate on record — their line is £0.00 until you set one.";
            }

            $period->entries()->create([
                'payable_type' => $payee->getMorphClass(),
                'payable_id' => $payee->getKey(),
                'staff_name' => $payee->name,
                'ni_number' => $payee instanceof StaffRosterMember ? $payee->safeNiNumber() : null,
                'hourly_rate' => $rate ?? 0,
            ]);
        }

        return $period;
    }

    /** Roster members and account holders, de-duplicated by name. */
    private function payees(): Collection
    {
        if ($this->payees !== null) {
            return $this->payees;
        }

        $roster = StaffRosterMember::where('active', true)->orderBy('name')->get();

        // Identity resolution: prefer the explicit staff_roster.user_id link.
        // Name matching is kept only as a fallback for rows not yet linked,
        // because two spellings of the same person used to become two payslips.
        $linkedUserIds = $roster->pluck('user_id')->filter()->all();
        $rosterNames = $roster->map(fn ($s) => mb_strtolower(trim($s->name)))->all();

        $users = User::orderBy('name')->get()
            ->reject(fn (User $u) => in_array($u->id, $linkedUserIds, true)
                || in_array(mb_strtolower(trim($u->name)), $rosterNames, true));

        return $this->payees = $roster->concat($users)->values();
    }
}
