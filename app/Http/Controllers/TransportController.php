<?php

namespace App\Http\Controllers;

use App\Events\TransportOutcomeRecorded;
use App\Events\TransportRunUndone;
use App\Models\Member;
use App\Models\TransportLedgerEntry;
use App\Models\TransportRun;
use Illuminate\Http\Request;
use App\Support\TransportCharges;
use Inertia\Inertia;

class TransportController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $today = $request->date('date') ?? today();

        $members = Member::scheduledFor($today)
            ->whereHas('settings', fn ($q) => $q->where('transport_required', true))
            ->with('settings')
            ->orderBy('first_name')
            ->get();

        $runs = TransportRun::whereDate('run_date', $today)->get()
            ->groupBy(fn ($r) => $r->member_id.':'.$r->phase);

        $rows = $members->map(function (Member $m) use ($runs, $today) {
            $address = collect([$m->address_line1, $m->address_line2, $m->town, $m->postcode])
                ->filter()->implode(', ');

            // Geocode once per address, ever — cached on the member record.
            if ($address && ! $m->geocoded_at) {
                $coords = \App\Support\Geocoder::resolve($address);
                $m->update([
                    'lat' => $coords['lat'] ?? null,
                    'lng' => $coords['lng'] ?? null,
                    'geocoded_at' => now(),
                ]);
            }

            $balance = TransportLedgerEntry::balanceFor($m->id);

            return [
                'id' => $m->id,
                'name' => $m->displayName(),
                'address' => $address ?: null,
                'lat' => $m->lat ? (float) $m->lat : null,
                'lng' => $m->lng ? (float) $m->lng : null,
                'phone' => $m->phone,
                'balance' => $balance,
                'days_credit' => (int) floor(max(0, $balance) / TransportLedgerEntry::DAILY_RATE),
                'legs_credit' => (int) floor(max(0, $balance) / TransportLedgerEntry::LEG_RATE),
                'morning_outcome' => optional($runs->get($m->id.':morning'))->first()?->outcome,
                'afternoon_outcome' => optional($runs->get($m->id.':afternoon'))->first()?->outcome,
                'morning_done' => $runs->has($m->id.':morning'),
                'afternoon_done' => $runs->has($m->id.':afternoon'),
                'todays_charge' => \App\Support\TransportCharges::chargedOn($m->id, $today),
                'legs_remaining' => \App\Support\TransportCredit::legsRemaining($m->id),
                'credit_low' => \App\Support\TransportCredit::isLow($m->id),
                'owes' => \App\Support\TransportCredit::owes($m->id),
                'suggested_top_up' => \App\Support\TransportCredit::suggestedTopUp($m),
                'ledger' => $m->transportLedger()->orderByDesc('entry_date')->orderByDesc('id')->limit(20)->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'type' => $e->type,
                        'amount' => (float) $e->amount,
                        'entry_date' => $e->entry_date->toDateString(),
                        'notes' => $e->notes,
                    ]),
            ];
        })
            // Owing first, then clear, then in credit (SPEC checklist).
            ->sortBy(fn ($r) => $r['balance'])
            ->values();

        $monthEntries = TransportLedgerEntry::whereBetween('entry_date', [
            $today->copy()->startOfMonth(),
            $today->copy()->endOfMonth(),
        ])->get();

        return Inertia::render('Transport', [
            'date' => $today->toDateString(),
            'isToday' => $today->isToday(),
            'rows' => $rows,
            'dailyRate' => TransportLedgerEntry::DAILY_RATE,
            'legRate' => TransportLedgerEntry::LEG_RATE,
            'outcomes' => ['collected', 'not_collected', 'absent'],
            'suggestedAmounts' => \App\Support\TransportCredit::SUGGESTED,
            'monthly' => [
                'charged' => (float) $monthEntries->where('type', 'charge')->sum('amount'),
                'collected' => (float) $monthEntries->where('type', 'payment')->sum('amount'),
            ],
        ]);
    }

    /**
     * Records the outcome of one leg. Three states, deliberately distinct:
     *
     *   collected     — travelled. £2.50 for this leg.
     *   not_collected — didn't take this leg but is still expected in
     *                   (appointment, own lift, going home early). No charge
     *                   for the leg, and the other leg is left alone.
     *   absent        — not in at all today. Cancels both legs, no charge.
     */
    public function outcome(Request $request, Member $member)
    {
        abort_unless(
            Member::scheduledFor(today())->whereKey($member->id)->exists(),
            422,
            "{$member->displayName()} isn't scheduled for transport today.",
        );

        $data = $request->validate([
            'phase' => ['required', 'in:morning,afternoon'],
            'outcome' => ['required', 'in:collected,not_collected,absent'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $run = TransportRun::updateOrCreate(
            ['run_date' => today(), 'member_id' => $member->id, 'phase' => $data['phase']],
            [
                'outcome' => $data['outcome'],
                'outcome_reason' => $data['reason'] ?? null,
                'completed_at' => $data['outcome'] === 'collected' ? now() : null,
                'user_id' => $request->user()->id,
            ],
        );

        // Recomputes the day's charge from the legs actually travelled, and
        // updates the register if this was an absence.
        TransportOutcomeRecorded::dispatch($run);

        $charge = TransportCharges::chargedOn($member->id, today());

        return back()->with('success', sprintf(
            '%s — %s. Today\'s transport charge: £%s.',
            $member->displayName(),
            str_replace('_', ' ', $data['outcome']),
            number_format($charge, 2),
        ));
    }

    /** Kept for the existing "mark collected" button. */
    public function complete(Request $request, Member $member)
    {
        $request->merge(['outcome' => 'collected']);

        return $this->outcome($request, $member);
    }

    /** Running statement of prepayments and charges for one member. */
    public function statement(Member $member)
    {
        return response()->json([
            'member' => $member->displayName(),
            'balance' => \App\Support\TransportCredit::balance($member->id),
            'legs_remaining' => \App\Support\TransportCredit::legsRemaining($member->id),
            'returns_remaining' => \App\Support\TransportCredit::returnsRemaining($member->id),
            'suggested_top_up' => \App\Support\TransportCredit::suggestedTopUp($member),
            'entries' => \App\Support\TransportCredit::statement($member->id),
        ]);
    }

    public function undo(Request $request, Member $member)
    {
        $data = $request->validate([
            'phase' => ['required', 'in:morning,afternoon'],
        ]);

        $run = TransportRun::whereDate('run_date', today())
            ->where('member_id', $member->id)
            ->where('phase', $data['phase'])
            ->first();

        if ($run) {
            // Delete first, then fire. The listener recomputes the day's charge
            // from the remaining legs, so the undone leg must already be gone —
            // otherwise it still counts and the charge never drops. The model
            // instance keeps its attributes after delete(), so the listener
            // still knows which member and date to recalculate.
            $run->delete();
            TransportRunUndone::dispatch($run);
        }

        if ($data['phase'] === 'morning') {
            TransportLedgerEntry::where('member_id', $member->id)
                ->where('type', 'charge')
                ->whereDate('entry_date', today())
                ->where('notes', 'Transport day charge')
                ->limit(1)
                ->delete();
        }

        return back()->with('success', 'Undone.');
    }

    public function pay(Request $request, Member $member)
    {
        abort_unless(
            Member::scheduledFor(today())->whereKey($member->id)->exists(),
            422,
            "{$member->displayName()} isn't scheduled for transport today.",
        );

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'entry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        TransportLedgerEntry::create([
            'member_id' => $member->id,
            'type' => 'payment',
            'amount' => $data['amount'],
            'entry_date' => $data['entry_date'] ?? today(),
            'method' => 'cash',
            'notes' => $data['notes'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', '£'.number_format($data['amount'], 2)." received from {$member->displayName()}. Please issue a receipt from the till.");
    }

    // Corrections only — payments recorded in error (SPEC checklist: delete payment).
    public function deletePayment(TransportLedgerEntry $entry)
    {
        abort_unless($entry->type === 'payment', 404);
        $entry->delete();

        return back()->with('success', 'Payment removed.');
    }
}
