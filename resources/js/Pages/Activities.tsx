import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';

interface ActivityRow {
    id: number;
    title: string;
    activity_date: string;
    start_time: string | null;
    description: string | null;
    user: string;
}

interface Props {
    month: string;
    activities: ActivityRow[];
    upcoming: { id: number; title: string; activity_date: string }[];
}

export default function Activities({ month, activities, upcoming }: Props) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        activity_date: new Date().toISOString().slice(0, 10),
        start_time: '',
        description: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/activities', { onSuccess: () => { setAdding(false); reset(); } });
    }

    const byDate = activities.reduce<Record<string, ActivityRow[]>>((acc, a) => {
        (acc[a.activity_date] ??= []).push(a);
        return acc;
    }, {});

    return (
        <AppShell title="Activities">
            <Head title="Activities" />

            <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                + Add activity
            </button>

            {upcoming.length > 0 && (
                <Card title="Coming up" className="mb-4">
                    <ul className="text-sm space-y-1">
                        {upcoming.map((u) => (
                            <li key={u.id}>
                                <b>{new Date(u.activity_date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}</b>
                                {' — '}{u.title}
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Card title={`This month (${month})`}>
                {activities.length === 0 && <p className="text-sm text-slate-400">Nothing logged this month yet.</p>}
                <div className="space-y-3">
                    {Object.entries(byDate).map(([date, rows]) => (
                        <div key={date}>
                            <div className="text-xs font-bold text-slate-400 uppercase">
                                {new Date(date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })}
                            </div>
                            <ul className="mt-1 space-y-1">
                                {rows.map((a) => (
                                    <li key={a.id} className="text-sm">
                                        <b className="text-brand-dark">{a.start_time && `${a.start_time} · `}{a.title}</b>
                                        {a.description && <span className="text-slate-500"> — {a.description}</span>}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </Card>

            <Modal open={adding} title="Add activity" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Activity
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="e.g. Guinea pig handling session" className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={data.activity_date} onChange={(e) => setData('activity_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Start time
                            <input type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Description
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
