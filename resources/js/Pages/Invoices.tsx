import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import EmptyState from '../components/EmptyState';
import Modal from '../components/Modal';
import SegmentedControl from '../components/SegmentedControl';
import { confirmDialog, promptDialog } from '../utils/dialogs';
import ModuleHero from '../components/ModuleHero';

interface Invoice {
    id: number;
    member: string;
    qb_reference: string;
    amount: number;
    invoice_date: string;
    due_date: string | null;
    paid_date: string | null;
    status: string;
    qb_url: string | null;
}

interface Props {
    invoices: Invoice[];
    summary: { outstanding: number; overdueCount: number; collected: number };
    members: { id: number; name: string }[];
}

const gbp = (n: number) => `£${n.toLocaleString('en-GB', { minimumFractionDigits: 2 })}`;
const STATUS_STYLE: Record<string, string> = {
    draft: 'bg-slate-200 text-slate-600',
    sent: 'bg-blue-100 text-blue-800',
    paid: 'bg-emerald-100 text-emerald-800',
    overdue: 'bg-red-100 text-red-800',
    cancelled: 'bg-slate-100 text-slate-400 line-through',
};

export default function Invoices({ invoices, summary, members }: Props) {
    const [adding, setAdding] = useState(false);
    const [statusFilter, setStatusFilter] = useState('all');
    const [selected, setSelected] = useState<number[]>([]);
    const filtered = statusFilter === 'all' ? invoices : invoices.filter((i) => i.status === statusFilter);
    const payableSelected = selected.filter((id) => {
        const inv = invoices.find((i) => i.id === id);
        return inv && inv.status !== 'paid' && inv.status !== 'cancelled';
    });

    function toggleSelected(id: number) {
        setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    }

    function bulkMarkPaid() {
        router.post('/invoices/bulk/paid', { ids: payableSelected }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    }

    const { data, setData, post, processing, reset } = useForm({
        member_id: members[0]?.id ?? 0,
        qb_reference: '',
        amount: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        status: 'sent',
        qb_url: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/invoices', { onSuccess: () => { setAdding(false); reset(); } });
    }

    return (
        <AppShell title="Invoice Tracker">
            <Head title="Invoices" />
            <ModuleHero eyebrow="Income management" title="Invoices" description="Create, track and manage invoices without losing sight of what is outstanding." icon="🧾" tone="green" />

            <p className="text-xs text-slate-400 mb-3">
                Invoices are raised in QuickBooks — the Hub tracks references and payment status.
            </p>

            <div className="grid grid-cols-3 gap-3 mb-4">
                <Card>
                    <div className="text-xl font-extrabold text-status-amber">{gbp(summary.outstanding)}</div>
                    <div className="text-xs text-slate-500 font-medium">Outstanding</div>
                </Card>
                <Card>
                    <div className="text-xl font-extrabold text-status-red">{summary.overdueCount}</div>
                    <div className="text-xs text-slate-500 font-medium">Overdue</div>
                </Card>
                <Card>
                    <div className="text-xl font-extrabold text-status-green">{gbp(summary.collected)}</div>
                    <div className="text-xs text-slate-500 font-medium">Collected</div>
                </Card>
            </div>

            <div className="flex flex-wrap items-center gap-2 mb-4">
                <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5">
                    + Track invoice
                </button>
                <div className="ml-auto">
                    <SegmentedControl
                        options={['all', 'sent', 'overdue', 'paid', 'draft'].map((s) => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) }))}
                        value={statusFilter}
                        onChange={setStatusFilter}
                    />
                </div>
            </div>

            {payableSelected.length > 0 && (
                <div className="flex items-center gap-2 mb-3 px-1">
                    <span className="text-xs text-ink/45">{payableSelected.length} selected</span>
                    <button
                        onClick={bulkMarkPaid}
                        className="rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5"
                    >
                        ✓ Mark {payableSelected.length} paid
                    </button>
                </div>
            )}

            <div className="space-y-2">
                {filtered.map((i) => (
                    <div key={i.id} className="flex items-start gap-2">
                        {i.status !== 'paid' && i.status !== 'cancelled' && (
                            <input
                                type="checkbox"
                                checked={selected.includes(i.id)}
                                onChange={() => toggleSelected(i.id)}
                                className="mt-5 shrink-0 rounded border-slate-300"
                                aria-label={`Select invoice ${i.qb_reference}`}
                            />
                        )}
                    <Card className="flex-1">
                        <div className="flex items-center justify-between gap-2">
                            <div className="min-w-0">
                                <div className="font-bold text-brand-dark">
                                    {i.qb_url ? (
                                        <a href={i.qb_url} target="_blank" rel="noreferrer" className="underline decoration-dotted">
                                            {i.qb_reference} ↗
                                        </a>
                                    ) : (
                                        i.qb_reference
                                    )}
                                    <span className="font-normal text-slate-500"> · {i.member}</span>
                                </div>
                                <div className="text-xs text-slate-400">
                                    {new Date(i.invoice_date).toLocaleDateString('en-GB')}
                                    {i.due_date && ` · due ${new Date(i.due_date).toLocaleDateString('en-GB')}`}
                                    {i.paid_date && ` · paid ${new Date(i.paid_date).toLocaleDateString('en-GB')}`}
                                </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <b>{gbp(i.amount)}</b>
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[i.status]}`}>
                                    {i.status}
                                </span>
                                {['sent', 'overdue'].includes(i.status) && (
                                    <button
                                        onClick={() => router.post(`/invoices/${i.id}/paid`)}
                                        className="rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5"
                                    >
                                        ✓ Paid
                                    </button>
                                )}
                                <button
                                    onClick={async () => {
                                        const due = await promptDialog('Update due date (YYYY-MM-DD), or leave blank to keep:', i.due_date ?? '');
                                        if (due === null) return;
                                        const status = await promptDialog('Status (draft/sent/paid/cancelled):', i.status);
                                        if (status === null) return;
                                        router.put(`/invoices/${i.id}`, { due_date: due || null, status });
                                    }}
                                    className="rounded-full bg-slate-100 text-slate-500 text-xs font-bold px-2.5 py-1.5"
                                    aria-label="Edit invoice"
                                >
                                    ✏️
                                </button>
                                <button
                                    onClick={async () => (await confirmDialog(`Remove ${i.qb_reference}?`)) && router.delete(`/invoices/${i.id}`)}
                                    className="rounded-full bg-slate-100 text-red-500 text-xs font-bold px-2.5 py-1.5"
                                    aria-label="Delete invoice"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </Card>
                    </div>
                ))}
                {filtered.length === 0 && <Card><EmptyState icon="🧾" text={`No invoices${statusFilter !== 'all' ? ` with status "${statusFilter}"` : ''}.`} /></Card>}
            </div>

            <Modal open={adding} title="Track QuickBooks invoice" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Member
                            <select value={data.member_id} onChange={(e) => setData('member_id', Number(e.target.value))} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            QB reference
                            <input value={data.qb_reference} onChange={(e) => setData('qb_reference', e.target.value)} placeholder="INV-0042" className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Amount (£)
                            <input type="number" step="0.01" min="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Status
                            <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                {['draft', 'sent', 'paid', 'cancelled'].map((s) => <option key={s}>{s}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Invoice date
                            <input type="date" value={data.invoice_date} onChange={(e) => setData('invoice_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Due date
                            <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        QuickBooks link (optional)
                        <input type="url" value={data.qb_url} onChange={(e) => setData('qb_url', e.target.value)} placeholder="https://app.qbo.intuit.com/…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Track invoice
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
