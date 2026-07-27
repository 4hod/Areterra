import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Item {
    id: number;
    title: string;
    category: string | null;
    due_date: string;
    notes: string | null;
    completed_at: string | null;
    overdue: boolean;
}

interface Props {
    items: Item[];
    summary: { overdue: number; dueSoon: number; complete: number };
    canManage: boolean;
}

export default function Compliance({ items, summary, canManage }: Props) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ title: '', category: '', due_date: '', notes: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/compliance', { onSuccess: () => { setAdding(false); reset(); } });
    }

    return (
        <AppShell title="Compliance">
            <Head title="Compliance" />
            <ModuleHero eyebrow="Governance" title="Compliance" description="Stay ahead of renewals, checks and evidence requirements." icon="✅" tone="amber" />

            <div className="grid grid-cols-3 gap-3 mb-4">
                <Card>
                    <div className="text-2xl font-extrabold text-status-red">{summary.overdue}</div>
                    <div className="text-xs text-slate-500 font-medium">Overdue</div>
                </Card>
                <Card>
                    <div className="text-2xl font-extrabold text-status-amber">{summary.dueSoon}</div>
                    <div className="text-xs text-slate-500 font-medium">Due in 30 days</div>
                </Card>
                <Card>
                    <div className="text-2xl font-extrabold text-status-green">{summary.complete}</div>
                    <div className="text-xs text-slate-500 font-medium">Complete</div>
                </Card>
            </div>

            {canManage && (
                <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + Compliance item
                </button>
            )}

            <div className="space-y-2">
                {items.map((i) => (
                    <Card key={i.id} className={i.overdue ? 'border-l-4 border-l-status-red' : i.completed_at ? 'opacity-70' : ''}>
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <div className={`font-bold ${i.completed_at ? 'text-slate-400 line-through' : 'text-brand-dark'}`}>
                                    {i.title}
                                </div>
                                <div className="text-xs text-slate-400">
                                    {i.category && `${i.category} · `}
                                    due {new Date(i.due_date).toLocaleDateString('en-GB')}
                                    {i.completed_at && ` · done ${new Date(i.completed_at).toLocaleDateString('en-GB')}`}
                                    {i.overdue && <span className="text-red-600 font-bold"> · OVERDUE</span>}
                                </div>
                                {i.notes && <p className="text-sm text-slate-500 mt-1">{i.notes}</p>}
                            </div>
                            {canManage && !i.completed_at && (
                                <button
                                    onClick={() => router.post(`/compliance/${i.id}/complete`)}
                                    className="shrink-0 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-2"
                                >
                                    ✓ Complete
                                </button>
                            )}
                        </div>
                    </Card>
                ))}
                {items.length === 0 && <Card><p className="text-slate-500">No compliance items yet.</p></Card>}
            </div>

            <Modal open={adding} title="New compliance item" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="e.g. Renew employers' liability insurance" className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Category
                            <input value={data.category} onChange={(e) => setData('category', e.target.value)} placeholder="Insurance, DBS…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Due date
                            <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Notes
                        <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add item
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
