import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';

interface Task {
    id: number;
    title: string;
    description: string | null;
    priority: 'low' | 'medium' | 'high';
    due_date: string | null;
    assigned_to: string | null;
    created_by: string;
    status: 'pending' | 'overdue' | 'complete';
    notes: string | null;
}

const PRIORITY_STYLE: Record<string, string> = {
    low: 'bg-slate-100 text-slate-600',
    medium: 'bg-amber-100 text-amber-800',
    high: 'bg-red-100 text-red-700',
};

export default function Maintenance({ tasks, staff, canManage }: { tasks: Task[]; staff: { id: number; name: string }[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ title: '', description: '', priority: 'medium', due_date: '', assigned_to: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/maintenance', { onSuccess: () => { setAdding(false); reset(); } });
    }

    const open = tasks.filter((t) => t.status !== 'complete');
    const done = tasks.filter((t) => t.status === 'complete');

    return (
        <AppShell title="Maintenance">
            <Head title="Maintenance" />

            {canManage && (
                <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + New task
                </button>
            )}

            <div className="space-y-2">
                {open.map((t) => (
                    <Card key={t.id} className={t.status === 'overdue' ? 'border-l-4 border-l-status-red' : ''}>
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-bold text-brand-dark">{t.title}</div>
                                <div className="text-xs text-slate-400">
                                    <span className={`rounded-full px-2 py-0.5 font-bold uppercase mr-1 ${PRIORITY_STYLE[t.priority]}`}>{t.priority}</span>
                                    {t.due_date && `due ${new Date(t.due_date).toLocaleDateString('en-GB')} · `}
                                    {t.assigned_to ? `assigned to ${t.assigned_to}` : 'unassigned'}
                                    {t.status === 'overdue' && <span className="text-red-600 font-bold"> · OVERDUE</span>}
                                </div>
                                {t.description && <p className="text-sm text-slate-500 mt-1">{t.description}</p>}
                            </div>
                            {canManage && (
                                <button onClick={() => router.post(`/maintenance/${t.id}/complete`)} className="shrink-0 rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-2">
                                    ✓ Complete
                                </button>
                            )}
                        </div>
                    </Card>
                ))}
                {open.length === 0 && <Card><p className="text-slate-500">No open tasks.</p></Card>}
            </div>

            {done.length > 0 && (
                <Card title={`Completed (${done.length})`} className="mt-4 opacity-70">
                    <ul className="text-sm space-y-1">
                        {done.map((t) => <li key={t.id} className="line-through text-slate-400">{t.title}</li>)}
                    </ul>
                </Card>
            )}

            <Modal open={adding} title="New maintenance task" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <label className="block text-sm font-medium">
                        Description
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Priority
                            <select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Due date
                            <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Assign to
                        <select value={data.assigned_to} onChange={(e) => setData('assigned_to', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            <option value="">Unassigned</option>
                            {staff.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add task
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
