import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';

interface Project {
    id: number;
    title: string;
    description: string | null;
    status: 'planning' | 'active' | 'on-hold' | 'complete';
    start_date: string | null;
    end_date: string | null;
    created_by: string;
}

const STATUS_STYLE: Record<string, string> = {
    planning: 'bg-slate-100 text-slate-600',
    active: 'bg-sky-100 text-sky-800',
    'on-hold': 'bg-amber-100 text-amber-800',
    complete: 'bg-emerald-100 text-emerald-800',
};

export default function Projects({ projects, canManage }: { projects: Project[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ title: '', description: '', status: 'planning', start_date: '', end_date: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/projects', { onSuccess: () => { setAdding(false); reset(); } });
    }

    return (
        <AppShell title="Projects">
            <Head title="Projects" />

            {canManage && (
                <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + New project
                </button>
            )}

            <div className="space-y-2">
                {projects.map((p) => (
                    <Card key={p.id}>
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-bold text-brand-dark">{p.title}</div>
                                <div className="text-xs text-slate-400">
                                    {p.start_date && new Date(p.start_date).toLocaleDateString('en-GB')}
                                    {p.end_date && ` – ${new Date(p.end_date).toLocaleDateString('en-GB')}`}
                                    {' · '}by {p.created_by}
                                </div>
                                {p.description && <p className="text-sm text-slate-500 mt-1">{p.description}</p>}
                            </div>
                            <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[p.status]}`}>{p.status}</span>
                        </div>
                    </Card>
                ))}
                {projects.length === 0 && <Card><p className="text-slate-500">No projects yet.</p></Card>}
            </div>

            <Modal open={adding} title="New project" onClose={() => setAdding(false)}>
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
                            Start date
                            <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            End date
                            <input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Status
                        <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="on-hold">On hold</option>
                            <option value="complete">Complete</option>
                        </select>
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Create project
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
