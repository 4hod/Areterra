<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\TransportLedgerEntry;
use App\Models\TransportRun;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransportController extends Controller
{
    public function index()
    {
        $today = today();

        $members = Member::active()
            ->whereHas('settings', fn ($q) => $q->where('transport_required', true))
            ->with('settings')
            ->orderBy('first_name')
            ->get();

        $runs = TransportRun::whereDate('run_date', $today)->get()
            ->groupBy(fn ($r) => $r->member_id.':'.$r->phase);

        $rows = $members->map(function (Member $m) use ($runs) {
            $address = collect([$m->address_line1, $m->address_line2, $m->town, $m->postcode])
                ->filter()->implode(', ');
            $balance = TransportLedgerEntry::balanceFor($m->id);

            return [
                'id' => $m->id,
                'name' => $m->displayName(),
                'address' => $address ?: null,
                'phone' => $m->phone,
                'balance' => $balance,
                'days_credit' => (int) floor(max(0, $balance) / TransportLedgerEntry::DAILY_RATE),
                'morning_done' => $runs->has($m->id.':morning'),
                'afternoon_done' => $runs->has($m->id.':afternoon'),
            ];
        })->values();

        $monthEntries = TransportLedgerEntry::whereBetween('entry_date', [
            today()->startOfMonth(),
            today()->endOfMonth(),
        ])->get();

        return Inertia::render('Transport', [
            'date' => $today->toDateString(),
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
        $data = $request->validate([
            'phase' => ['required', 'in:morning,afternoon'],
        ]);

        TransportRun::firstOrCreate(
            ['run_date' => today(), 'member_id' => $member->id, 'phase' => $data['phase']],
            ['completed_at' => now(), 'user_id' => $request->user()->id],
        );

        // Charge auto-applies on morning collection, once per day (SPEC.md note 7).
        if ($data['phase'] === 'morning') {
            $alreadyCharged = TransportLedgerEntry::where('member_id', $member->id)
                ->where('type', 'charge')
                ->whereDate('entry_date', today())
                ->exists();

            if (! $alreadyCharged) {
                TransportLedgerEntry::create([
                    'member_id' => $member->id,
                    'type' => 'charge',
                    'amount' => TransportLedgerEntry::DAILY_RATE,
                    'entry_date' => today(),
                    'notes' => 'Transport day charge',
                    'user_id' => $request->user()->id,
                ]);
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
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        TransportLedgerEntry::create([
            'member_id' => $member->id,
            'type' => 'payment',
            'amount' => $data['amount'],
            'entry_date' => today(),
            'method' => 'cash',
            'notes' => $data['notes'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', '£'.number_format($data['amount'], 2)." received from {$member->displayName()}.");
    }
}
