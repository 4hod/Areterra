<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\StaffRosterMember;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index()
    {
        return Inertia::render('Payroll/Index', [
            'periods' => PayrollPeriod::withCount('entries')
                ->orderByDesc('start_date')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'label' => $p->label,
                    'start_date' => $p->start_date->toDateString(),
                    'end_date' => $p->end_date->toDateString(),
                    'pay_date' => $p->pay_date?->toDateString(),
                    'status' => $p->status,
                    'entries_count' => $p->entries_count,
                    'total' => (float) $p->entries()->sum('total'),
                ]),
            'roster' => StaffRosterMember::orderBy('name')->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'ni_number' => $s->ni_number,
                'job_title' => $s->job_title,
                'active' => $s->active,
                'current_rate' => $s->currentRate(),
            ]),
        ]);
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['nullable', 'date'],
            'authorised_by' => ['nullable', 'string', 'max:100'],
        ]);

        $period = PayrollPeriod::create($data);

        // Prefill a row for each active roster member at their current rate.
        StaffRosterMember::where('active', true)->orderBy('name')->get()
            ->each(fn (StaffRosterMember $s) => $period->entries()->create([
                'payable_type' => StaffRosterMember::class,
                'payable_id' => $s->id,
                'staff_name' => $s->name,
                'ni_number' => $s->ni_number,
                'hourly_rate' => $s->currentRate() ?? 0,
            ]));

        return redirect()->route('payroll.show', $period)->with('success', 'Pay period created.');
    }

    public function show(PayrollPeriod $period)
    {
        return Inertia::render('Payroll/Show', [
            'period' => $this->periodProps($period),
        ]);
    }

    public function print(PayrollPeriod $period)
    {
        return Inertia::render('Payroll/Print', [
            'period' => $this->periodProps($period),
        ]);
    }

    public function updatePeriod(Request $request, PayrollPeriod $period)
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'pay_date' => ['nullable', 'date'],
            'authorised_by' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', 'in:draft,finalised,paid'],
        ]);

        $period->update($data);

        return back()->with('success', 'Period updated.');
    }

    public function saveEntries(Request $request, PayrollPeriod $period)
    {
        if ($period->isLocked() && $request->missing('force')) {
            return back()->with('error', 'This period is finalised — set it back to draft to edit.');
        }

        $data = $request->validate([
            'entries' => ['required', 'array'],
            'entries.*.id' => ['nullable', 'integer'],
            'entries.*.staff_name' => ['required', 'string', 'max:100'],
            'entries.*.ni_number' => ['nullable', 'string', 'max:20'],
            'entries.*.hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'entries.*.total_hours' => ['nullable', 'numeric', 'min:0'],
            'entries.*.holiday_pay' => ['nullable', 'numeric', 'min:0'],
            'entries.*.total_ssp' => ['nullable', 'numeric', 'min:0'],
            'entries.*.mileage' => ['nullable', 'numeric', 'min:0'],
            'entries.*.mileage_pay' => ['nullable', 'numeric', 'min:0'],
            'deleted' => ['nullable', 'array'],
            'deleted.*' => ['integer'],
        ]);

        $period->entries()->whereIn('id', $data['deleted'] ?? [])->delete();

        foreach ($data['entries'] as $e) {
            $values = [
                'staff_name' => $e['staff_name'],
                'ni_number' => $e['ni_number'] ?? null,
                'hourly_rate' => $e['hourly_rate'] ?? 0,
                'total_hours' => $e['total_hours'] ?? 0,
                'holiday_pay' => $e['holiday_pay'] ?? 0,
                'total_ssp' => $e['total_ssp'] ?? 0,
                'mileage' => $e['mileage'] ?? 0,
                'mileage_pay' => $e['mileage_pay'] ?? 0,
            ];
            // Totals are always recomputed server-side.
            $values = [...$values, ...PayrollEntry::computeTotals($values)];

            if (! empty($e['id'])) {
                $period->entries()->whereKey($e['id'])->update($values);
            } else {
                $period->entries()->create($values);
            }
        }

        return back()->with('success', 'Payroll saved.');
    }

    public function destroyPeriod(PayrollPeriod $period)
    {
        $period->delete();

        return redirect()->route('payroll.index')->with('success', 'Period deleted.');
    }

    public function storeRosterMember(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ni_number' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = StaffRosterMember::create(collect($data)->except('hourly_rate')->all());

        if (! empty($data['hourly_rate'])) {
            $member->rates()->create([
                'hourly_rate' => $data['hourly_rate'],
                'effective_from' => today(),
            ]);
        }

        return back()->with('success', "{$member->name} added to the roster.");
    }

    public function updateRosterMember(Request $request, StaffRosterMember $rosterMember)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'ni_number' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'rate_effective_from' => ['nullable', 'date'],
        ]);

        $rosterMember->update(collect($data)->except(['hourly_rate', 'rate_effective_from'])->all());

        // A new rate is a new history row, preserving the old one (rate history).
        if (isset($data['hourly_rate']) && (float) $data['hourly_rate'] !== $rosterMember->currentRate()) {
            $rosterMember->rates()->create([
                'hourly_rate' => $data['hourly_rate'],
                'effective_from' => $data['rate_effective_from'] ?? today(),
            ]);
        }

        return back()->with('success', 'Roster updated.');
    }

    private function periodProps(PayrollPeriod $period): array
    {
        return [
            'id' => $period->id,
            'label' => $period->label,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'pay_date' => $period->pay_date?->toDateString(),
            'pay_date_display' => $period->pay_date?->format('jS F Y'),
            'authorised_by' => $period->authorised_by,
            'status' => $period->status,
            'entries' => $period->entries()->orderBy('staff_name')->get()->map(fn ($e) => [
                'id' => $e->id,
                'staff_name' => $e->staff_name,
                'ni_number' => $e->ni_number,
                'hourly_rate' => (float) $e->hourly_rate,
                'total_hours' => (float) $e->total_hours,
                'basic_pay' => (float) $e->basic_pay,
                'holiday_pay' => (float) $e->holiday_pay,
                'total_ssp' => (float) $e->total_ssp,
                'mileage' => (float) $e->mileage,
                'mileage_pay' => (float) $e->mileage_pay,
                'total' => (float) $e->total,
            ]),
        ];
    }
}
