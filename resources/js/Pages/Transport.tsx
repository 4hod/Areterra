import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import { confirmDialog } from '../utils/dialogs';

interface LedgerEntry {
    id: number;
    type: string;
    amount: number;
    entry_date: string;
    notes: string | null;
}

interface Row {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    balance: number;
    days_credit: number;
    morning_done: boolean;
    afternoon_done: boolean;
    ledger: LedgerEntry[];
}

interface Props {
    date: string;
    isToday: boolean;
    rows: Row[];
    dailyRate: number;
    monthly: { charged: number; collected: number };
}

const gbp = (n: number) => `£${Math.abs(n).toFixed(2)}`;

function FeeStatus({ row }: { row: Row }) {
    if (row.days_credit > 0) {
        return <span className="text-emerald-700 text-xs font-bold">{row.days_credit} day{row.days_credit === 1 ? '' : 's'} in credit</span>;
    }
    if (row.balance < 0) {
        return <span className="text-red-600 text-xs font-bold">Owes {gbp(row.balance)}</span>;
    }
    return <span className="text-slate-400 text-xs font-semibold">Paid up</span>;
}

export default function Transport({ date, isToday, rows, dailyRate, monthly }: Props) {
    const [paying, setPaying] = useState<Row | null>(null);
    const [amount, setAmount] = useState<number>(dailyRate);
    const [payDate, setPayDate] = useState(new Date().toISOString().slice(0, 10));
    const [payNotes, setPayNotes] = useState('');
    const [receipt, setReceipt] = useState<{ name: string; amount: number; balance: number } | null>(null);

    const morningQueue = rows.filter((r) => !r.morning_done);
    const morningDone = rows.filter((r) => r.morning_done);
    const morningComplete = rows.length > 0 && morningQueue.length === 0;
    const afternoonQueue = rows.filter((r) => !r.afternoon_done);
    const afternoonComplete = morningComplete && afternoonQueue.length === 0;
    const phase = afternoonComplete ? 3 : morningComplete ? 2 : 1;

    const next = phase === 1 ? morningQueue[0] : phase === 2 ? afternoonQueue[0] : null;

    function complete(row: Row, p: 'morning' | 'afternoon') {
        router.post(`/transport/${row.id}/complete`, { phase: p });
    }

    function undo(row: Row, p: 'morning' | 'afternoon') {
        router.post(`/transport/${row.id}/undo`, { phase: p });
    }

    function pay() {
        if (!paying) return;
        const member = paying;
        router.post(
            `/transport/${member.id}/pay`,
            { amount, entry_date: payDate, notes: payNotes },
            {
                onSuccess: () => {
                    setReceipt({ name: member.name, amount, balance: member.balance + amount });
                    setPaying(null);
                    setPayNotes('');
                },
            },
        );
    }

    return (
        <AppShell title="Transport">
            <Head title="Transport" />

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
                    <div className="flex flex-wrap gap-2 mt-4">
                        <button
                            onClick={() => complete(next, phase === 2 ? 'afternoon' : 'morning')}
                            className="rounded-full bg-white text-brand-dark font-bold px-5 py-2.5"
                        >
                            {phase === 2 ? 'Mark as Dropped Off ✓' : 'Mark as Collected ✓'}
                        </button>
                        <button
                            onClick={() => setPaying(next)}
                            className="rounded-full bg-accent text-brand-dark font-bold px-4 py-2.5"
                        >
                            💷 Take Payment
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
                                <button
                                    onClick={() => complete(r, 'morning')}
                                    className="rounded-full bg-slate-100 font-semibold text-xs px-3 py-2"
                                >
                                    Collected ✓
                                </button>
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
                                <button
                                    onClick={() => complete(r, 'afternoon')}
                                    className="rounded-full bg-slate-100 font-semibold text-xs px-3 py-2"
                                >
                                    Dropped ✓
                                </button>
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
                                        <span className="ml-2 text-emerald-700 text-xs font-bold">
                                            ✓ collected{r.afternoon_done ? ' · dropped off' : ''}
                                        </span>
                                        <div><FeeStatus row={r} /></div>
                                    </div>
                                    <div className="flex gap-1">
                                        <button
                                            onClick={() => setPaying(r)}
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
                                onClick={() => setPaying(r)}
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
                            </>
                        )}
                    </p>
                    <div className="flex gap-2">
                        {[5, 10, 15, 20].map((v) => (
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
                            = {Math.floor(amount / dailyRate)} day{Math.floor(amount / dailyRate) === 1 ? '' : 's'}
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
                        Save & Receipt
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

            {/* Receipt modal (print-friendly) */}
            <Modal open={receipt !== null} title="Transport Receipt" onClose={() => setReceipt(null)}>
                {receipt && (
                    <div id="receipt" className="text-center space-y-2 print:block">
                        <div className="text-2xl font-extrabold text-brand-dark">Areterra</div>
                        <div className="text-xs text-slate-500">Transport Receipt · {new Date(date).toLocaleDateString('en-GB')}</div>
                        <div className="text-lg font-bold">{receipt.name}</div>
                        <div className="text-3xl font-extrabold text-brand">{gbp(receipt.amount)}</div>
                        <div className="text-sm text-slate-500">
                            Rate £{dailyRate.toFixed(2)}/day · {Math.floor(receipt.amount / dailyRate)} day(s) covered
                        </div>
                        <div className="text-sm">
                            New balance:{' '}
                            <b className={receipt.balance < 0 ? 'text-red-600' : 'text-emerald-700'}>
                                {receipt.balance < 0 ? `−${gbp(receipt.balance)}` : gbp(receipt.balance)}
                            </b>
                        </div>
                        <div className="text-[10px] text-slate-400 pt-2">
                            Areterra · Registered charity No. 1196211 · 01562 307 306
                        </div>
                        <button
                            onClick={() => window.print()}
                            className="mt-2 rounded-full bg-brand text-white font-bold text-sm px-5 py-2.5 print:hidden"
                        >
                            🖨 Print receipt
                        </button>
                    </div>
                )}
            </Modal>
        </AppShell>
    );
}
