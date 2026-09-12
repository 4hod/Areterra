<?php

namespace App\Http\Controllers;

use App\Models\AdditionalIncome;
use App\Models\FixedCost;
use App\Models\Grant;
use App\Models\InKindDonation;
use App\Models\Member;
use App\Models\MemberFinanceProfile;
use App\Models\MemberInvoice;
use App\Models\Setting;
use App\Support\Period;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FinanceController extends Controller
{
    private const DEFAULT_RATES = [
        'full_day' => 60,
        'half_day' => 0,
        'one_to_one_hourly' => 21,
        'transport_day' => 5,
    ];

    public function index()
    {
        $rates = collect(self::DEFAULT_RATES)->mapWithKeys(fn ($default, $key) => [
            $key => (float) Setting::get("finance_rate_{$key}", (string) $default),
        ])->all();

        $members = Member::active()->with(['settings', 'financeProfile'])->orderBy('first_name')->get();
        $memberRows = $members->map(function (Member $member) use ($rates) {
            $profile = $member->financeProfile;
            $daysPerWeek = count($member->settings?->attendance_days ?? []);
            $attendanceType = $profile?->attendance_type ?? 'full_day';
            $dayRate = (float) ($profile?->custom_day_rate ?? $rates[$attendanceType] ?? $rates['full_day']);
            $oneToOneHours = (float) ($profile?->one_to_one_hours_per_week ?? 0);
            $oneToOneRate = (float) ($profile?->custom_one_to_one_rate ?? $rates['one_to_one_hourly']);
            $chargeTransport = $profile?->charge_transport ?? (bool) ($member->settings?->transport_required ?? false);
            $transportRate = (float) ($profile?->custom_transport_rate ?? $rates['transport_day']);
            $attendanceIncome = $daysPerWeek * 4 * $dayRate;
            $oneToOneIncome = $oneToOneHours * 4 * $oneToOneRate;
            $transportIncome = $chargeTransport ? $daysPerWeek * 4 * $transportRate : 0;

            return [
                'id' => $member->id,
                'name' => $member->displayName(),
                'initials' => strtoupper(substr($member->preferred_name ?: $member->first_name, 0, 1).substr($member->last_name, 0, 1)),
                'days_per_week' => $daysPerWeek,
                'attendance_days' => $member->settings?->attendance_days ?? [],
                'attendance_type' => $attendanceType,
                'day_rate' => $dayRate,
                'one_to_one_hours_per_week' => $oneToOneHours,
                'one_to_one_rate' => $oneToOneRate,
                'charge_transport' => $chargeTransport,
                'transport_rate' => $transportRate,
                'attendance_income' => round($attendanceIncome, 2),
                'one_to_one_income' => round($oneToOneIncome, 2),
                'transport_income' => round($transportIncome, 2),
                'four_week_income' => round($attendanceIncome + $oneToOneIncome + $transportIncome, 2),
            ];
        });

        $additionalIncome = AdditionalIncome::orderByDesc('active')->orderBy('description')->get();
        $fixedCosts = FixedCost::orderByDesc('active')->orderBy('category')->orderBy('description')->get();
        $grants = Grant::with('expenditures')->orderByDesc('start_date')->get();
        $invoices = MemberInvoice::all();

        $memberIncome = (float) $memberRows->sum('four_week_income');
        $otherIncome = (float) $additionalIncome->where('active', true)->sum(fn ($row) => $this->toFourWeekly((float) $row->amount, $row->frequency));
        $costs = (float) $fixedCosts->where('active', true)->sum(fn ($row) => $this->toFourWeekly((float) $row->amount, $row->frequency));
        $totalIncome = $memberIncome + $otherIncome;

        return Inertia::render('Finance', [
            'rates' => $rates,
            'members' => $memberRows->values(),
            'additionalIncome' => $additionalIncome->map(fn ($row) => [
                'id' => $row->id,
                'description' => $row->description,
                'amount' => (float) $row->amount,
                'frequency' => $row->frequency,
                'four_week_amount' => round($this->toFourWeekly((float) $row->amount, $row->frequency), 2),
                'active' => $row->active,
                'notes' => $row->notes,
            ]),
            'fixedCosts' => $fixedCosts->map(fn ($row) => [
                'id' => $row->id,
                'description' => $row->description,
                'category' => $row->category,
                'amount' => (float) $row->amount,
                'frequency' => $row->frequency,
                'four_week_amount' => round($this->toFourWeekly((float) $row->amount, $row->frequency), 2),
                'active' => $row->active,
                'notes' => $row->notes,
            ]),
            'grants' => $grants->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'funder' => $g->funder,
                'amount' => (float) $g->amount,
                'spent' => $g->spent(),
                'start_date' => $g->start_date?->toDateString(),
                'end_date' => $g->end_date?->toDateString(),
                'status' => $g->status,
                'expenditures' => $g->expenditures->map(fn ($e) => [
                    'id' => $e->id,
                    'description' => $e->description,
                    'amount' => (float) $e->amount,
                    'spent_date' => $e->spent_date->toDateString(),
                ]),
            ]),
            'summary' => [
                'memberIncome' => round($memberIncome, 2),
                'otherIncome' => round($otherIncome, 2),
                'totalIncome' => round($totalIncome, 2),
                'costs' => round($costs, 2),
                'surplus' => round($totalIncome - $costs, 2),
                'costPercentage' => $totalIncome > 0 ? round(($costs / $totalIncome) * 100, 1) : 0,
                'grantIncome' => (float) $grants->whereIn('status', ['active', 'completed'])->sum('amount'),
                'grantSpend' => (float) $grants->sum(fn ($g) => $g->spent()),
                'inKindValue' => (float) InKindDonation::sum('estimated_value'),
                'invoiced' => (float) $invoices->where('status', '!=', 'cancelled')->sum('amount'),
                'collected' => (float) $invoices->where('status', 'paid')->sum('amount'),
            ],
        ]);
    }

    public function updateRates(Request $request)
    {
        $data = $request->validate([
            'full_day' => ['required', 'numeric', 'min:0'],
            'half_day' => ['required', 'numeric', 'min:0'],
            'one_to_one_hourly' => ['required', 'numeric', 'min:0'],
            'transport_day' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set("finance_rate_{$key}", number_format((float) $value, 2, '.', ''));
        }

        return back()->with('success', 'Finance rates updated.');
    }

    public function updateMemberProfile(Request $request, Member $member)
    {
        $data = $request->validate([
            'attendance_type' => ['required', 'in:full_day,half_day'],
            'custom_day_rate' => ['nullable', 'numeric', 'min:0'],
            'one_to_one_hours_per_week' => ['required', 'numeric', 'min:0'],
            'custom_one_to_one_rate' => ['nullable', 'numeric', 'min:0'],
            'charge_transport' => ['required', 'boolean'],
            'custom_transport_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        MemberFinanceProfile::updateOrCreate(['member_id' => $member->id], $data);

        return back()->with('success', 'Member finance settings updated.');
    }

    public function storeAdditionalIncome(Request $request)
    {
        AdditionalIncome::create($this->validateRecurringRow($request));
        return back()->with('success', 'Additional income added.');
    }

    public function updateAdditionalIncome(Request $request, AdditionalIncome $additionalIncome)
    {
        $additionalIncome->update($this->validateRecurringRow($request));
        return back()->with('success', 'Additional income updated.');
    }

    public function destroyAdditionalIncome(AdditionalIncome $additionalIncome)
    {
        $additionalIncome->delete();
        return back()->with('success', 'Additional income removed.');
    }

    public function storeFixedCost(Request $request)
    {
        FixedCost::create($this->validateRecurringRow($request, true));
        return back()->with('success', 'Fixed cost added.');
    }

    public function updateFixedCost(Request $request, FixedCost $fixedCost)
    {
        $fixedCost->update($this->validateRecurringRow($request, true));
        return back()->with('success', 'Fixed cost updated.');
    }

    public function destroyFixedCost(FixedCost $fixedCost)
    {
        $fixedCost->delete();
        return back()->with('success', 'Fixed cost removed.');
    }

    private function validateRecurringRow(Request $request, bool $withCategory = false): array
    {
        $rules = [
            'description' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'],
            'frequency' => ['required', 'in:weekly,four_weekly,monthly,quarterly,annually,one_off'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
        ];
        if ($withCategory) $rules['category'] = ['nullable', 'string', 'max:100'];
        return $request->validate($rules);
    }

    // Delegates to the central period engine so Finance, payroll, invoicing and
    // forecasting can never drift apart. See App\Support\Period.
    private function toFourWeekly(float $amount, string $frequency): float
    {
        return Period::toFourWeekly($amount, $frequency);
    }

    public function storeGrant(Request $request)
    {
        Grant::create($request->validate([
            'title' => ['required', 'string', 'max:200'], 'funder' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'min:0'], 'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:applied,active,completed,declined'], 'notes' => ['nullable', 'string'],
        ]));
        return back()->with('success', 'Grant added.');
    }

    public function updateGrant(Request $request, Grant $grant)
    {
        $grant->update($request->validate([
            'status' => ['sometimes', 'in:applied,active,completed,declined'],
            'amount' => ['sometimes', 'numeric', 'min:0'], 'notes' => ['nullable', 'string'],
        ]));
        return back()->with('success', 'Grant updated.');
    }

    public function storeExpenditure(Request $request, Grant $grant)
    {
        // Q15 — a closed grant can still be spent against, but only by a manager.
        if (in_array($grant->status, ['closed', 'completed'], true)
            && ! $request->user()->hasCapability('manage_finance')) {
            throw new \App\Exceptions\WorkflowException(
                ["{$grant->title} is closed. Recording further spend against it needs manager approval."],
                'This grant is closed.',
            );
        }

        $expenditure = $grant->expenditures()->create([
            ...$request->validate(['description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'numeric', 'min:0.01'], 'spent_date' => ['required', 'date']]),
            'user_id' => $request->user()->id,
        ]);

        // Posts to the ledger against the grant, so restricted spend never
        // inflates unrestricted reserves. See Listeners\PostGrantExpenseToLedger.
        \App\Events\GrantExpenseRecorded::dispatch($expenditure->load('grant'));

        // Q14 — overspend is allowed, but never silently.
        $spent = (float) $grant->expenditures()->sum('amount');
        $warning = $spent > (float) $grant->amount
            ? sprintf(
                '%s is now overspent by £%s (£%s of £%s).',
                $grant->title,
                number_format($spent - (float) $grant->amount, 2),
                number_format($spent, 2),
                number_format((float) $grant->amount, 2),
            )
            : null;

        return back()
            ->with('success', 'Expenditure recorded.')
            ->with('problems', $warning ? [$warning] : []);
    }
}
