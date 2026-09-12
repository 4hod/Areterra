<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\StaffRosterMember;
use App\Support\PayrollRates;
use App\Workflows\Payroll\ApprovePayrollPeriod;
use App\Workflows\Payroll\OpenPayrollPeriod;
use App\Workflows\Payroll\PayrollPreview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'key' => "roster:{$s->id}",
                'name' => $s->name,
                'ni_number' => $s->safeNiNumber(),
                'job_title' => $s->job_title,
                'email' => $s->email,
                'phone' => $s->phone,
                'active' => $s->active,
                'has_account' => false,
                'current_rate' => $s->currentRate(),
                'contracted_hours' => $s->rates()->orderByDesc('effective_from')->value('contracted_hours'),
            ])->concat(
                \App\Models\User::orderBy('name')->get()->map(fn ($u) => [
                    'id' => $u->id,
                    'key' => "user:{$u->id}",
                    'name' => $u->name,
                    'ni_number' => null,
                    'job_title' => $u->job_title,
                    'email' => $u->email,
                    'phone' => null,
                    'active' => true,
                    'has_account' => true,
                    'current_rate' => ($r = $u->rates()->where('effective_from', '<=', today())->orderByDesc('effective_from')->first()) ? (float) $r->hourly_rate : null,
                    'contracted_hours' => $u->rates()->orderByDesc('effective_from')->value('contracted_hours'),
                ]),
            )->values(),
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

        // Validates, then creates period + entries in a single transaction.
        // A missing rate no longer becomes a silent 0.00 — it comes back as a
        // named warning. See OpenPayrollPeriod.
        $workflow = new OpenPayrollPeriod($data);
        $period = $workflow->run();

        return redirect()->route('payroll.show', $period)
            ->with('success', 'Pay period created.')
            ->with('problems', $workflow->warnings);
    }

    public function show(PayrollPeriod $period)
    {
        return Inertia::render('Payroll/Show', [
            'period' => $this->periodProps($period),
            // Every figure, its working, and anything blocking approval.
            'preview' => PayrollPreview::for($period)->toArray(),
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

        // One transaction: a failure part-way through the loop can no longer
        // leave some lines saved, some deleted and the rest lost.
        DB::transaction(function () use ($data, $period) {
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
        });

        return back()
            ->with('success', 'Payroll saved.')
            ->with('problems', PayrollPreview::for($period)->problems());
    }

    /**
     * Draft -> Approved. Blocked, by name, if anything is wrong.
     * WorkflowException renders the reasons rather than a 500.
     */
    public function approve(Request $request, PayrollPeriod $period)
    {
        $data = $request->validate([
            'approved_by' => ['required', 'string', 'max:100'],
        ]);

        (new ApprovePayrollPeriod($period, $data['approved_by']))->run();

        return back()->with('success', 'Pay run approved.');
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $member = StaffRosterMember::create(collect($data)->except('hourly_rate')->all());

        if (! empty($data['hourly_rate'])) {
            $member->rates()->create([
                'hourly_rate' => $data['hourly_rate'],
                'overtime_rate' => round($data['hourly_rate'] * 1.5, 2),
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
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $rosterMember->update($data);

        return back()->with('success', 'Roster updated.');
    }

    public function destroyRosterMember(StaffRosterMember $rosterMember)
    {
        $rosterMember->delete();

        return back()->with('success', "{$rosterMember->name} removed from the roster.");
    }

    // New rate = new history row (roster staff or system users alike).
    public function setRate(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string'], // "roster:1" or "user:2"
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
            'contracted_hours' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ]);

        [$kind, $id] = explode(':', $data['key']);
        $payee = ($kind === 'user' ? \App\Models\User::class : StaffRosterMember::class)::findOrFail($id);

        $payee->rates()->create([
            'hourly_rate' => $data['hourly_rate'],
            // Overtime auto-fills at 1.5× unless given explicitly (SPEC checklist).
            'overtime_rate' => $data['overtime_rate'] ?? round($data['hourly_rate'] * 1.5, 2),
            'contracted_hours' => $data['contracted_hours'] ?? null,
            'effective_from' => $data['effective_from'] ?? today(),
        ]);

        return back()->with('success', "Rate updated for {$payee->name}.");
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
                'ni_number' => $e->safeNiNumber(),
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
