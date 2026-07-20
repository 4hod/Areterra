import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';

interface Expenditure {
    id: number;
    description: string;
    amount: number;
    spent_date: string;
}

interface GrantRow {
    id: number;
    title: string;
    funder: string;
    amount: number;
    spent: number;
    start_date: string | null;
    end_date: string | null;
    status: string;
    expenditures: Expenditure[];
}

interface InKindRow {
    id: number;
    donor: string;
    type: string | null;
    category: string | null;
    estimated_value: number;
    quantity: number;
    date: string;
    grant: string | null;
}

interface Props {
    grants: GrantRow[];
    inKind: InKindRow[];
    summary: { grantIncome: number; grantSpend: number; inKindValue: number; invoiced: number; collected: number };
}

const gbp = (n: number) => `£${n.toLocaleString('en-GB', { minimumFractionDigits: 2 })}`;

export default function Finance({ grants, inKind, summary }: Props) {
    const [addingGrant, setAddingGrant] = useState(false);
    const [addingInKind, setAddingInKind] = useState(false);
    const [spendingOn, setSpendingOn] = useState<GrantRow | null>(null);

    const grantForm = useForm({ title: '', funder: '', amount: '', start_date: '', end_date: '', status: 'active', notes: '' });
    const inKindForm = useForm({ donor: '', type: '', category: '', estimated_value: '', quantity: 1, date: new Date().toISOString().slice(0, 10), grant_id: '' as string | number, notes: '' });
    const spendForm = useForm({ description: '', amount: '', spent_date: new Date().toISOString().slice(0, 10) });

    function submitGrant(e: FormEvent) {
        e.preventDefault();
        grantForm.post('/finance/grants', { onSuccess: () => { setAddingGrant(false); grantForm.reset(); } });
    }

    function submitInKind(e: FormEvent) {
        e.preventDefault();
        inKindForm.transform((d) => ({ ...d, grant_id: d.grant_id || null }));
        inKindForm.post('/finance/in-kind', { onSuccess: () => { setAddingInKind(false); inKindForm.reset(); } });
    }

    function submitSpend(e: FormEvent) {
        e.preventDefault();
        if (!spendingOn) return;
        spendForm.post(`/finance/grants/${spendingOn.id}/expenditures`, {
            onSuccess: () => { setSpendingOn(null); spendForm.reset(); },
        });
    }

    return (
        <AppShell title="Finance & Grants">
            <Head title="Finance" />

            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <Card>
                    <div className="text-xl font-extrabold text-brand-dark">{gbp(summary.grantIncome)}</div>
                    <div className="text-xs text-slate-500 font-medium">Grant income</div>
                </Card>
                <Card>
                    <div className="text-xl font-extrabold text-status-amber">{gbp(summary.grantSpend)}</div>
                    <div className="text-xs text-slate-500 font-medium">Grant spend</div>
                </Card>
                <Card>
                    <div className="text-xl font-extrabold text-status-green">{gbp(summary.inKindValue)}</div>
                    <div className="text-xs text-slate-500 font-medium">In-kind value</div>
                </Card>
                <Card>
                    <div className="text-xl font-extrabold text-brand">{gbp(summary.collected)}</div>
                    <div className="text-xs text-slate-500 font-medium">Fees collected</div>
                </Card>
            </div>

            <div className="flex gap-2 mb-4">
                <button onClick={() => setAddingGrant(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5">
                    + Grant
                </button>
                <button onClick={() => setAddingInKind(true)} className="rounded-full bg-brand-dark text-white font-semibold text-sm px-5 py-2.5">
                    + In-kind donation
                </button>
            </div>

            <Card title="Grants" className="mb-4">
                {grants.length === 0 && <p className="text-sm text-slate-400">No grants tracked yet.</p>}
                <div className="space-y-3">
                    {grants.map((g) => {
                        const pct = g.amount > 0 ? Math.min(100, (g.spent / g.amount) * 100) : 0;
                        return (
                            <div key={g.id} className="rounded-lg border border-slate-200 p-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="font-bold text-brand-dark">{g.title}</div>
                                        <div className="text-xs text-slate-400">
                                            {g.funder} · {g.status}
                                            {g.end_date && ` · ends ${new Date(g.end_date).toLocaleDateString('en-GB')}`}
                                        </div>
                                    </div>
                                    <button onClick={() => setSpendingOn(g)} className="rounded-full bg-slate-100 text-xs font-bold px-3 py-2">
                                        + Spend
                                    </button>
                                </div>
                                <div className="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
                                    <div className="h-full bg-brand" style={{ width: `${pct}%` }} />
                                </div>
                                <div className="mt-1 text-xs text-slate-500">
                                    {gbp(g.spent)} spent of {gbp(g.amount)}
                                </div>
                                {g.expenditures.length > 0 && (
                                    <details className="mt-1">
                                        <summary className="text-xs font-bold text-brand cursor-pointer">Expenditure ({g.expenditures.length})</summary>
                                        <ul className="mt-1 text-xs text-slate-500 space-y-0.5">
                                            {g.expenditures.map((e) => (
                                                <li key={e.id} className="flex justify-between">
                                                    <span>{new Date(e.spent_date).toLocaleDateString('en-GB')} — {e.description}</span>
                                                    <b>{gbp(e.amount)}</b>
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                )}
                            </div>
                        );
                    })}
                </div>
            </Card>

            <Card title="In-kind donations">
                {inKind.length === 0 && <p className="text-sm text-slate-400">No in-kind donations recorded.</p>}
                <ul className="divide-y divide-slate-100 text-sm">
                    {inKind.map((d) => (
                        <li key={d.id} className="py-2 flex items-center justify-between">
                            <div>
                                <span className="font-semibold">{d.donor}</span>
                                <span className="text-slate-400 text-xs">
                                    {' '}· {d.type}{d.category && ` · ${d.category}`} × {d.quantity}
                                    {d.grant && ` · ${d.grant}`}
                                </span>
                            </div>
                            <b>{gbp(d.estimated_value)}</b>
                        </li>
                    ))}
                </ul>
            </Card>

            <Modal open={addingGrant} title="Add grant" onClose={() => setAddingGrant(false)}>
                <form onSubmit={submitGrant} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input value={grantForm.data.title} onChange={(e) => grantForm.setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Funder
                            <input value={grantForm.data.funder} onChange={(e) => grantForm.setData('funder', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Amount (£)
                            <input type="number" step="0.01" min="0" value={grantForm.data.amount} onChange={(e) => grantForm.setData('amount', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Start
                            <input type="date" value={grantForm.data.start_date} onChange={(e) => grantForm.setData('start_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            End
                            <input type="date" value={grantForm.data.end_date} onChange={(e) => grantForm.setData('end_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Status
                        <select value={grantForm.data.status} onChange={(e) => grantForm.setData('status', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            {['applied', 'active', 'completed', 'declined'].map((s) => <option key={s}>{s}</option>)}
                        </select>
                    </label>
                    <button type="submit" disabled={grantForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add grant
                    </button>
                </form>
            </Modal>

            <Modal open={addingInKind} title="Record in-kind donation" onClose={() => setAddingInKind(false)}>
                <form onSubmit={submitInKind} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Donor
                        <input value={inKindForm.data.donor} onChange={(e) => inKindForm.setData('donor', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Type
                            <input value={inKindForm.data.type} onChange={(e) => inKindForm.setData('type', e.target.value)} placeholder="goods, services…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Category
                            <input value={inKindForm.data.category} onChange={(e) => inKindForm.setData('category', e.target.value)} placeholder="animal feed…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Value (£)
                            <input type="number" step="0.01" min="0" value={inKindForm.data.estimated_value} onChange={(e) => inKindForm.setData('estimated_value', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Quantity
                            <input type="number" min="1" value={inKindForm.data.quantity} onChange={(e) => inKindForm.setData('quantity', Number(e.target.value))} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={inKindForm.data.date} onChange={(e) => inKindForm.setData('date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Linked grant
                            <select value={inKindForm.data.grant_id} onChange={(e) => inKindForm.setData('grant_id', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                <option value="">None</option>
                                {grants.map((g) => <option key={g.id} value={g.id}>{g.title}</option>)}
                            </select>
                        </label>
                    </div>
                    <button type="submit" disabled={inKindForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Record donation
                    </button>
                </form>
            </Modal>

            <Modal open={spendingOn !== null} title={`Expenditure — ${spendingOn?.title ?? ''}`} onClose={() => setSpendingOn(null)}>
                <form onSubmit={submitSpend} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Description
                        <input value={spendForm.data.description} onChange={(e) => spendForm.setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Amount (£)
                            <input type="number" step="0.01" min="0.01" value={spendForm.data.amount} onChange={(e) => spendForm.setData('amount', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={spendForm.data.spent_date} onChange={(e) => spendForm.setData('spent_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                    </div>
                    <button type="submit" disabled={spendForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Record spend
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
