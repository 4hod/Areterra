<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\TransportLedgerEntry;
use App\Models\TransportRun;
use Illuminate\Http\Request;
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

        $rows = $members->map(function (Member $m) use ($runs) {
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
                'morning_done' => $runs->has($m->id.':morning'),
                'afternoon_done' => $runs->has($m->id.':afternoon'),
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
            'monthly' => [
                'charged' => (float) $monthEntries->where('type', 'charge')->sum('amount'),
                'collected' => (float) $monthEntries->where('type', 'payment')->sum('amount'),
            ],
        ]);
    }

    public function complete(Request $request, Member $member)
    {
        abort_unless(
            Member::scheduledFor(today())->whereKey($member->id)->exists(),
            422,
            "{$member->displayName()} isn't scheduled for transport today.",
        );

        $data = $request->validate([
            'phase' => ['required', 'in:morning,afternoon'],
        ]);

        TransportRun::firstOrCreate(
            ['run_date' => today(), 'member_id' => $member->id, 'phase' => $data['phase']],
            ['completed_at' => now(), 'user_id' => $request->user()->id],
        );

        // Charge auto-applies on morning collection, once per day (SPEC.md note 7).
        // The pre-check below is just a fast path for the common case — the
        // unique index on (member_id, charge_date) is what actually
        // guarantees no double-charge, even under near-simultaneous requests.
        if ($data['phase'] === 'morning') {
            $alreadyCharged = TransportLedgerEntry::where('member_id', $member->id)
                ->where('type', 'charge')
                ->whereDate('entry_date', today())
                ->exists();

            if (! $alreadyCharged) {
                try {
                    TransportLedgerEntry::create([
                        'member_id' => $member->id,
                        'type' => 'charge',
                        'amount' => TransportLedgerEntry::DAILY_RATE,
                        'entry_date' => today(),
                        'charge_date' => today(),
                        'notes' => 'Transport day charge',
                        'user_id' => $request->user()->id,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    // Another request already charged this member today — fine, no-op.
                }
            }
        }

        return back()->with('success', "{$member->displayName()} marked as ".($data['phase'] === 'morning' ? 'collected' : 'dropped off').'.');
    }

    public function undo(Request $request, Member $member)
    {
        $data = $request->validate([
            'phase' => ['required', 'in:morning,afternoon'],
        ]);

        TransportRun::whereDate('run_date', today())
            ->where('member_id', $member->id)
            ->where('phase', $data['phase'])
            ->delete();

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
