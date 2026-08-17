import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import EmptyState from '../components/EmptyState';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface SarRequestRow {
    id: number;
    requester_name: string;
    requester_relationship: string | null;
    member: string | null;
    received_date: string;
    deadline_date: string;
    status: 'pending' | 'in_progress' | 'fulfilled' | 'declined';
    fulfilled_date: string | null;
    notes: string | null;
    is_overdue: boolean;
}

const STATUS_STYLE: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    in_progress: 'bg-sky-100 text-sky-800',
    fulfilled: 'bg-emerald-100 text-emerald-800',
    declined: 'bg-slate-200 text-slate-600',
};

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

export default function SarRequests({ requests, members }: { requests: SarRequestRow[]; members: { id: number; name: string }[] }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        requester_name: '',
        requester_relationship: '',
        member_id: '',
        received_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/sar-requests', { onSuccess: () => { setAdding(false); reset(); } });
    }

    function setStatus(row: SarRequestRow, status: string) {
        router.put(`/sar-requests/${row.id}`, { status, notes: row.notes }, { preserveScroll: true });
    }

    return (
        <AppShell title="SAR Requests">
            <Head title="SAR Requests" />
            <ModuleHero
                eyebrow="Information rights"
                title="Subject Access Requests"
                description="Track incoming SAR requests against the statutory one-month deadline, separate from generating the data extract itself."
                icon="🔐"
                tone="slate"
            />

            <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                + Log a request
            </button>

            <div className="space-y-2">
                {requests.map((r) => (
                    <Card key={r.id} className={r.is_overdue ? 'border-l-4 border-l-status-red' : ''}>
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-bold text-brand-dark">
                                    {r.requester_name}
                                    {r.requester_relationship && <span className="font-normal text-slate-500"> ({r.requester_relationship})</span>}
                                    {r.member && <span className="font-normal text-slate-500"> · re: {r.member}</span>}
                                </div>
                                <div className="text-xs text-slate-400">
                                    Received {fmt(r.received_date)} · Deadline {fmt(r.deadline_date)}
                                    {r.is_overdue && <span className="text-status-red font-bold"> · OVERDUE</span>}
                                    {r.fulfilled_date && ` · Fulfilled ${fmt(r.fulfilled_date)}`}
                                </div>
                                {r.notes && <p className="text-sm text-slate-500 mt-1">{r.notes}</p>}
                            </div>
                            <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[r.status]}`}>
                                {r.status.replace('_', ' ')}
                            </span>
                        </div>
                        {r.status !== 'fulfilled' && r.status !== 'declined' && (
                            <div className="flex gap-1.5 mt-2">
                                {r.status === 'pending' && (
                                    <button onClick={() => setStatus(r, 'in_progress')} className="rounded-full bg-sky-100 text-sky-800 text-xs font-bold px-3 py-1.5">
                                        Mark in progress
                                    </button>
                                )}
                                <button onClick={() => setStatus(r, 'fulfilled')} className="rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5">
                                    ✓ Mark fulfilled
                                </button>
                                <button onClick={() => setStatus(r, 'declined')} className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1.5">
                                    Declined
                                </button>
                            </div>
                        )}
                    </Card>
                ))}
                {requests.length === 0 && <Card><EmptyState icon="🔐" text="No SAR requests logged." /></Card>}
            </div>

            <Modal open={adding} title="Log a SAR request" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Requester name
                        <input value={data.requester_name} onChange={(e) => setData('requester_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Relationship (optional)
                            <input value={data.requester_relationship} onChange={(e) => setData('requester_relationship', e.target.value)} placeholder="self, parent, social worker…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Received date
                            <input type="date" value={data.received_date} onChange={(e) => setData('received_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Relating to member (optional)
                        <select value={data.member_id} onChange={(e) => setData('member_id', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            <option value="">— Not linked to a specific member —</option>
                            {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                        </select>
                    </label>
                    <label className="block text-sm font-medium">
                        Notes
                        <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <p className="text-xs text-ink/40">Deadline will be set automatically to one calendar month from the received date.</p>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Log request
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
