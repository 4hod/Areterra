import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import TransportMap from '../components/TransportMap';
import Modal from '../components/Modal';
import { confirmDialog } from '../utils/dialogs';
import ModuleHero from '../components/ModuleHero';

interface LedgerEntry {
    id: number;
    type: string;
    amount: number;
    entry_date: string;
    notes: string | null;
}

type Outcome = 'collected' | 'not_collected' | 'absent';

interface StatementRow {
    id: number;
    date: string;
    type: string;
    amount: number;
    balance_after: number;
    notes: string | null;
}

interface Row {
    id: number;
    name: string;
    address: string | null;
    lat: number | null;
    lng: number | null;
    phone: string | null;
    balance: number;
    days_credit: number;
    legs_credit: number;
    legs_remaining: number;
    credit_low: boolean;
    owes: boolean;
    suggested_top_up: number;
    todays_charge: number;
    morning_outcome: Outcome | null;
    afternoon_outcome: Outcome | null;
    morning_done: boolean;
    afternoon_done: boolean;
    ledger: LedgerEntry[];
}

interface Props {
    date: string;
    isToday: boolean;
    rows: Row[];
    dailyRate: number;
    legRate: number;
    outcomes: Outcome[];
    suggestedAmounts: number[];
    monthly: { charged: number; collected: number };
}

const gbp = (n: number) => `£${Math.abs(n).toFixed(2)}`;

function FeeStatus({ row }: { row: Row }) {
    if (row.owes) {
        return <span className="text-red-600 text-xs font-bold">Owes {gbp(row.balance)}</span>;
    }
    if (row.legs_remaining > 0) {
        const tone = row.credit_low ? 'text-amber-600' : 'text-emerald-700';
        return (
            <span className={`${tone} text-xs font-bold`}>
                {gbp(row.balance)} left · {row.legs_remaining} journey{row.legs_remaining === 1 ? '' : 's'}
                {row.credit_low && ' — running low'}
            </span>
        );
    }
    return <span className="text-slate-400 text-xs font-semibold">No credit</span>;
}

const OUTCOME_LABEL: Record<Outcome, string> = {
    collected: 'Collected',
    not_collected: 'Not collected',
    absent: 'Absent all day',
};

export default function Transport({ date, isToday, rows, dailyRate, legRate, suggestedAmounts, monthly }: Props) {
    const [paying, setPaying] = useState<Row | null>(null);
    const [statement, setStatement] = useState<{ member: string; balance: number; returns_remaining: number; entries: StatementRow[] } | null>(null);
    const [amount, setAmount] = useState<number>(dailyRate);
    const [payDate, setPayDate] = useState(new Date().toISOString().slice(0, 10));
    const [payNotes, setPayNotes] = useState('');


    const morningQueue = rows.filter((r) => !r.morning_outcome);
    const morningDone = rows.filter((r) => r.morning_outcome);
    const morningComplete = rows.length > 0 && morningQueue.length === 0;
    const afternoonQueue = rows.filter((r) => !r.afternoon_outcome && r.morning_outcome !== 'absent');
    const afternoonComplete = morningComplete && afternoonQueue.length === 0;
    const phase = afternoonComplete ? 3 : morningComplete ? 2 : 1;

    const next = phase === 1 ? morningQueue[0] : phase === 2 ? afternoonQueue[0] : null;

    function openStatement(row: Row) {
        fetch(`/transport/${row.id}/statement`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then(setStatement);
    }

    function record(row: Row, p: 'morning' | 'afternoon', outcome: Outcome, reason?: string) {
        router.post(`/transport/${row.id}/outcome`, { phase: p, outcome, reason: reason ?? null });
    }

    function complete(row: Row, p: 'morning' | 'afternoon') {
        record(row, p, 'collected');
    }

    function askReason(row: Row, p: 'morning' | 'afternoon', outcome: Outcome) {
        const prompt = outcome === 'absent'
            ? `Why is ${row.name} absent today?`
            : `Why wasn't ${row.name} collected? (they're still expected in)`;
        const reason = window.prompt(prompt) ?? undefined;
        if (reason === undefined) return;
        record(row, p, outcome, reason);
    }

    function undo(row: Row, p: 'morning' | 'afternoon') {
        router.post(`/transport/${row.id}/undo`, { phase: p });
    }

    function pay() {
        if (!paying) return;
        router.post(
            `/transport/${paying.id}/pay`,
            { amount, entry_date: payDate, notes: payNotes },
            {
                onSuccess: () => {
                    setPaying(null);
                    setPayNotes('');
                },
            },
        );
    }

    return (
        <AppShell title="Transport">
            <Head title="Transport" />
            <ModuleHero eyebrow="Daily journeys" title="Transport" description="Coordinate collections, drop-offs and transport payments in one live workspace." icon="🚐" tone="purple" />

            {/* Date picker */}
            <div className="flex items-center gap-2 mb-3">
                <input
                    type="date"
                    value={date}
                    onChange={(e) => router.get('/transport', { date: e.target.value })}
                    className="rounded-lg border border-slate-300 px-3 bg-white text-sm"
                />
                {!isToday && <span className="text-xs font-bold text-amber-600">Viewing a different day</span>}
            </div>

            {/* Morning summary banner during afternoon phase */}
            {phase === 2 && (
                <div className="rounded-card bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold px-4 py-2.5 mb-3">
                    ✓ Morning complete — all {rows.length} collected. Now dropping off.
                </div>
            )}

            {/* Progress stepper */}
            <div className="flex items-center gap-2 mb-4 text-xs font-bold">
                {['Morning Collection', 'Afternoon Drop-off', 'Complete'].map((label, i) => (
                    <div key={label} className="flex items-center gap-2">
                        <span
                            className={`h-7 w-7 rounded-full flex items-center justify-center text-white ${
                                phase > i + 1 ? 'bg-status-green' : phase === i + 1 ? (i === 1 ? 'bg-purple-600' : 'bg-brand') : 'bg-slate-300'
                            }`}
                        >
                            {phase > i + 1 ? '✓' : i + 1}
                        </span>
                        <span className={phase === i + 1 ? 'text-brand-dark' : 'text-slate-400'}>{label}</span>
                        {i < 2 && <span className="text-slate-300">—</span>}
                    </div>
                ))}
            </div>

            {rows.length > 0 && (
                <Card title="🗺️ Today's stops" className="mb-4">
                    <TransportMap stops={rows.map((r) => ({ id: r.id, name: r.name, address: r.address, lat: r.lat, lng: r.lng }))} />
                </Card>
            )}

            {rows.length === 0 && (
                <Card>
                    <p className="text-slate-500">
                        No members have transport enabled. Turn it on from a member's Settings tab.
                    </p>
                </Card>
            )}

            {/* Next-person card */}
            {next && (
                <div className={`rounded-card p-5 text-white mb-4 ${phase === 2 ? 'bg-purple-700' : 'bg-brand'}`}>
                    <div className="text-xs font-bold uppercase tracking-wide opacity-80">
                        {phase === 2 ? 'Next drop-off' : 'Next to collect'}
                    </div>
                    <div className="text-2xl font-extrabold mt-1">{next.name}</div>
                    {next.address ? (
                        <div className="mt-1 opacity-90">{next.address}</div>
                    ) : (
                        <div className="mt-1 font-semibold text-accent">⚠ No address on file</div>
                    )}
                    <div className="mt-1">
                        <FeeStatus row={next} />
                    </div>
                    <div className="mt-1 text-xs opacity-80">
                        Today so far: {gbp(next.todays_charge)}
                        {next.todays_charge === 0 && ' — nothing charged yet'}
                        {' · '}{gbp(legRate)} a journey
                    </div>
                    <div className="flex flex-wrap gap-2 mt-4">
                        <button
                            onClick={() => complete(next, phase === 2 ? 'afternoon' : 'morning')}
                            className="rounded-full bg-white text-brand-dark font-bold px-5 py-2.5"
                        >
                            {phase === 2 ? 'Dropped off ✓' : 'Collected ✓'}
                        </button>
                        <button
                            onClick={() => askReason(next, phase === 2 ? 'afternoon' : 'morning', 'not_collected')}
                            className="rounded-full bg-white/20 font-semibold px-4 py-2.5"
                        >
                            Not collected
                        </button>
                        <button
                            onClick={() => askReason(next, phase === 2 ? 'afternoon' : 'morning', 'absent')}
                            className="rounded-full bg-white/20 font-semibold px-4 py-2.5"
                        >
                            Absent all day
                        </button>
                        <button
                            onClick={() => { setPaying(next); setAmount(next.suggested_top_up || dailyRate); }}
                            className="rounded-full bg-accent text-brand-dark font-bold px-4 py-2.5"
                        >
                            💷 Take Payment
                        </button>
                        <button
                            onClick={() => openStatement(next)}
                            className="rounded-full bg-white/20 font-semibold px-4 py-2.5"
                        >
                            Statement
                        </button>
                        {next.address && (
                            <a
                                href={`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(next.address)}`}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-full bg-white/20 font-semibold px-4 py-2.5"
                            >
                                🧭 Navigate
                            </a>
                        )}
                        {next.phone && (
                            <a href={`tel:${next.phone}`} className="rounded-full bg-white/20 font-semibold px-4 py-2.5">
                                📞 Call
                            </a>
                        )}
                    </div>
                </div>
            )}

            {phase === 3 && rows.length > 0 && (
                <Card className="mb-4 text-center border-l-4 border-l-status-green">
                    <div className="text-4xl mb-1">🎉</div>
                    <div className="font-bold text-brand-dark">Transport complete for today</div>
                </Card>
            )}

            {/* Remaining queue */}
            {phase === 1 && morningQueue.length > 1 && (
                <Card title="Still to collect" className="mb-4">
                    <ul className="divide-y divide-slate-100">
                        {morningQueue.slice(1).map((r) => (
                            <li key={r.id} className="py-2 flex items-center justify-between text-sm">
                                <div>
                                    <span className="font-semibold">{r.name}</span>
                                    {!r.address && <span className="ml-2 text-amber-600 text-xs font-bold">⚠ no address</span>}
                                    <div><FeeStatus row={r} /></div>
                                </div>
                                <div className="flex gap-1 shrink-0">
                                    <button
                                        onClick={() => complete(r, 'morning')}
                                        className="rounded-full bg-slate-100 font-semibold text-xs px-3 py-2"
                                    >
                                        Collected ✓
                                    </button>
                                    <button
                                        onClick={() => askReason(r, 'morning', 'not_collected')}
                                        className="rounded-full bg-slate-100 text-slate-600 font-semibold text-xs px-3 py-2"
                                    >
                                        Not collected
                                    </button>
                                    <button
                                        onClick={() => askReason(r, 'morning', 'absent')}
                                        className="rounded-full bg-slate-100 text-red-700 font-semibold text-xs px-3 py-2"
                                    >
                                        Absent
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
            {phase === 2 && afternoonQueue.length > 1 && (
                <Card title="Still to drop off" className="mb-4">
                    <ul className="divide-y divide-slate-100">
                        {afternoonQueue.slice(1).map((r) => (
                            <li key={r.id} className="py-2 flex items-center justify-between text-sm">
                                <span className="font-semibold">{r.name}</span>
                                <div className="flex gap-1 shrink-0">
                                    <button
                                        onClick={() => complete(r, 'afternoon')}
                                        className="rounded-full bg-slate-100 font-semibold text-xs px-3 py-2"
                                    >
                                        Dropped ✓
                                    </button>
                                    <button
                                        onClick={() => askReason(r, 'afternoon', 'not_collected')}
                                        className="rounded-full bg-slate-100 text-slate-600 font-semibold text-xs px-3 py-2"
                                    >
                                        Not collected
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            {/* Completed disclosure */}
            {morningDone.length > 0 && (
                <details className="mb-4">
                    <summary className="text-sm font-semibold text-brand cursor-pointer">
                        Completed today ({morningDone.length})
                    </summary>
                    <div className="mt-2 space-y-2">
                        {morningDone.map((r) => (
                            <Card key={r.id}>
                                <div className="flex items-center justify-between text-sm">
                                    <div>
                                        <span className="font-semibold">{r.name}</span>
                                        <span className={`ml-2 text-xs font-bold ${r.morning_outcome === 'collected' ? 'text-emerald-700' : r.morning_outcome === 'absent' ? 'text-red-600' : 'text-slate-500'}`}>
                                            {r.morning_outcome ? OUTCOME_LABEL[r.morning_outcome] : ''}
                                            {r.afternoon_outcome && ` · ${OUTCOME_LABEL[r.afternoon_outcome].toLowerCase()} home`}
                                            {' · '}{gbp(r.todays_charge)}
                                        </span>
                                        <div><FeeStatus row={r} /></div>
                                    </div>
                                    <div className="flex gap-1">
                                        <button
                                            onClick={() => { setPaying(r); setAmount(r.suggested_top_up || dailyRate); }}
                                            className="rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs px-3 py-2"
                                        >
                                            💷 Payment
                                        </button>
                                        <button
                                            onClick={() => undo(r, r.afternoon_done ? 'afternoon' : 'morning')}
                                            className="rounded-full bg-slate-100 text-slate-500 font-semibold text-xs px-3 py-2"
                                        >
                                            Undo
                                        </button>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                </details>
            )}

            {/* Take payment from anyone */}
            {rows.length > 0 && (
                <Card title="Monthly summary">
                    <div className="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <div className="text-xl font-extrabold text-brand-dark">{gbp(monthly.charged)}</div>
                            <div className="text-xs text-slate-500 font-medium">Charged</div>
                        </div>
                        <div>
                            <div className="text-xl font-extrabold text-status-green">{gbp(monthly.collected)}</div>
                            <div className="text-xs text-slate-500 font-medium">Collected</div>
                        </div>
                        <div>
                            <div className="text-xl font-extrabold text-status-amber">
                                {gbp(Math.max(0, monthly.charged - monthly.collected))}
                            </div>
                            <div className="text-xs text-slate-500 font-medium">Outstanding</div>
                        </div>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-1.5">
                        {rows.map((r) => (
                            <button
                                key={r.id}
                                onClick={() => { setPaying(r); setAmount(r.suggested_top_up || dailyRate); }}
                                className="rounded-full border border-slate-200 text-xs font-semibold px-3 py-1.5 hover:bg-slate-50"
                            >
                                {r.name.split(' ')[0]} · <FeeStatus row={r} />
                            </button>
                        ))}
                    </div>
                </Card>
            )}

            {/* Payment modal */}
            <Modal open={paying !== null} title={`Payment — ${paying?.name ?? ''}`} onClose={() => setPaying(null)}>
                <div className="space-y-4">
                    <p className="text-sm text-slate-500">
                        £{dailyRate.toFixed(2)}/day, cash. £{(dailyRate * 2).toFixed(2)} = 2 days credit.
                        {paying && (
                            <>
                                {' '}Current balance:{' '}
                                <b className={paying.balance < 0 ? 'text-red-600' : 'text-emerald-700'}>
                                    {paying.balance < 0 ? `−${gbp(paying.balance)}` : gbp(paying.balance)}
                                </b>
                                {paying.suggested_top_up > 0 && (
                                    <> · covers a fortnight with {gbp(paying.suggested_top_up)}</>
                                )}
                            </>
                        )}
                    </p>
                    <div className="flex gap-2">
                        {suggestedAmounts.map((v) => (
                            <button
                                key={v}
                                onClick={() => setAmount(v)}
                                className={`flex-1 rounded-lg py-3 font-bold ${
                                    amount === v ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'
                                }`}
                            >
                                £{v}
                            </button>
                        ))}
                    </div>
                    <label className="block text-sm font-medium">
                        Other amount
                        <input
                            type="number"
                            min={0.5}
                            step={0.5}
                            value={amount}
                            onChange={(e) => setAmount(Number(e.target.value))}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                        <span className="text-xs font-bold text-brand">
                            = {Math.floor(amount / legRate)} journey{Math.floor(amount / legRate) === 1 ? '' : 's'}
                            {' · '}{Math.floor(amount / dailyRate)} full day{Math.floor(amount / dailyRate) === 1 ? '' : 's'}
                        </span>
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={payDate} onChange={(e) => setPayDate(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Notes
                            <input value={payNotes} onChange={(e) => setPayNotes(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <button onClick={pay} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        Save payment
                    </button>

                    {/* Payment & charge history */}
                    {paying && paying.ledger.length > 0 && (
                        <details>
                            <summary className="text-xs font-bold text-brand cursor-pointer">History</summary>
                            <ul className="mt-1 text-xs space-y-0.5 max-h-40 overflow-y-auto">
                                {paying.ledger.map((e) => (
                                    <li key={e.id} className="flex items-center justify-between">
                                        <span className={e.type === 'payment' ? 'text-emerald-700' : 'text-slate-500'}>
                                            {new Date(e.entry_date).toLocaleDateString('en-GB')} — {e.type === 'payment' ? 'payment' : 'charge'}
                                            {e.notes && ` (${e.notes})`}
                                        </span>
                                        <span className="flex items-center gap-2 font-bold">
                                            {e.type === 'payment' ? '+' : '−'}{gbp(e.amount)}
                                            {e.type === 'payment' && (
                                                <button
                                                    onClick={async () => (await confirmDialog('Delete this payment?')) && router.delete(`/transport/payments/${e.id}`, { onSuccess: () => setPaying(null) })}
                                                    className="text-red-400"
                                                    aria-label="Delete payment"
                                                >
                                                    ✕
                                                </button>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </details>
                    )}
                </div>
            </Modal>
            <Modal open={statement !== null} title={statement ? `${statement.member} — transport account` : ''} onClose={() => setStatement(null)}>
                {statement && (
                    <div className="space-y-3">
                        <div className="rounded-card bg-slate-50 px-4 py-3">
                            <div className="text-2xl font-extrabold text-brand-dark">
                                {statement.balance < 0 ? `−${gbp(statement.balance)}` : gbp(statement.balance)}
                            </div>
                            <div className="text-sm text-slate-600">
                                {statement.balance < 0
                                    ? 'owed'
                                    : `in credit · ${statement.returns_remaining} full day${statement.returns_remaining === 1 ? '' : 's'} covered`}
                            </div>
                        </div>

                        {statement.entries.length === 0 && (
                            <p className="text-sm text-slate-500">No payments or charges recorded yet.</p>
                        )}

                        <ul className="divide-y divide-slate-100">
                            {statement.entries.map((row) => (
                                <li key={row.id} className="py-2 flex items-baseline justify-between gap-3 text-sm">
                                    <div className="min-w-0">
                                        <div className="text-slate-700">{row.notes ?? (row.amount > 0 ? 'Payment' : 'Transport')}</div>
                                        <div className="text-xs text-slate-400">
                                            {new Date(row.date).toLocaleDateString('en-GB')}
                                        </div>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <div className={row.amount > 0 ? 'font-bold text-emerald-700' : 'font-semibold text-slate-700'}>
                                            {row.amount > 0 ? '+' : '−'}{gbp(row.amount)}
                                        </div>
                                        <div className="text-xs text-slate-400">{gbp(row.balance_after)} left</div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </Modal>
        </AppShell>
    );
}
