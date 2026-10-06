import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import EmptyState from '../components/EmptyState';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Entry {
    id: number;
    title: string;
    funder: string | null;
    amount: number | null;
    deadline: string | null;
    status: 'identified' | 'applied' | 'awarded' | 'declined';
    link: string | null;
    notes: string | null;
}

const STATUS_STYLE: Record<string, string> = {
    identified: 'bg-slate-100 text-slate-600',
    applied: 'bg-sky-100 text-sky-800',
    awarded: 'bg-emerald-100 text-emerald-800',
    declined: 'bg-red-100 text-red-700',
};

export default function Funding({ entries, canManage }: { entries: Entry[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<Entry | null>(null);
    const { data, setData, post, put, processing, reset } = useForm({ title: '', funder: '', amount: '', deadline: '', status: 'identified', link: '', notes: '' });

    function openNew() { reset(); setEditing(null); setAdding(true); }
    function openEdit(entry: Entry) {
        setData({ title: entry.title, funder: entry.funder ?? '', amount: entry.amount === null ? '' : String(entry.amount), deadline: entry.deadline ?? '', status: entry.status, link: entry.link ?? '', notes: entry.notes ?? '' });
        setEditing(entry); setAdding(true);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        const options = { onSuccess: () => { setAdding(false); setEditing(null); reset(); } };
        if (editing) put(`/funding/${editing.id}`, options); else post('/funding', options);
    }

    return (
        <AppShell title="Funding">
            <Head title="Funding" />
            <ModuleHero eyebrow="Growth & sustainability" title="Funding" description="Track opportunities, deadlines and applications from first idea to decision." icon="🌱" tone="green" />

            {canManage && (
                <button onClick={openNew} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + New opportunity
                </button>
            )}

            <div className="space-y-2">
                {entries.map((f) => (
                    <Card key={f.id}>
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-bold text-brand-dark">
                                    {f.link ? <a href={f.link} target="_blank" rel="noreferrer" className="underline">{f.title}</a> : f.title}
                                </div>
                                <div className="text-xs text-slate-400">
                                    {f.funder && `${f.funder} · `}
                                    {f.amount !== null && `£${f.amount.toLocaleString()} · `}
                                    {f.deadline && `deadline ${new Date(f.deadline).toLocaleDateString('en-GB')}`}
                                </div>
                                {f.notes && <p className="text-sm text-slate-500 mt-1">{f.notes}</p>}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[f.status]}`}>{f.status}</span>
                                {canManage && <button onClick={() => openEdit(f)} className="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700">Edit</button>}
                            </div>
                        </div>
                    </Card>
                ))}
                {entries.length === 0 && <Card><EmptyState icon="💰" text="No funding opportunities logged yet." /></Card>}
            </div>

            <Modal open={adding} title={editing ? 'Edit funding opportunity' : 'New funding opportunity'} onClose={() => { setAdding(false); setEditing(null); }}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Funder
                            <input value={data.funder} onChange={(e) => setData('funder', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Amount £
                            <input type="number" min="0" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Deadline
                            <input type="date" value={data.deadline} onChange={(e) => setData('deadline', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Status
                            <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                <option value="identified">Identified</option>
                                <option value="applied">Applied</option>
                                <option value="awarded">Awarded</option>
                                <option value="declined">Declined</option>
                            </select>
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Link
                        <input type="url" value={data.link} onChange={(e) => setData('link', e.target.value)} placeholder="https://…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Notes
                        <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        {editing ? 'Save opportunity' : 'Add opportunity'}
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
