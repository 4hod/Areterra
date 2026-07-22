import { Head, Link, useForm } from '@inertiajs/react';
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
    month: string; // 'YYYY-MM'
    activities: ActivityRow[];
    upcoming: { id: number; title: string; activity_date: string }[];
    prevMonth: string;
    nextMonth: string;
}

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function Activities({ month, activities, upcoming, prevMonth, nextMonth }: Props) {
    const [adding, setAdding] = useState(false);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
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

    function openAddFor(dateStr: string) {
        setData('activity_date', dateStr);
        setAdding(true);
    }

    const byDate = activities.reduce<Record<string, ActivityRow[]>>((acc, a) => {
        (acc[a.activity_date] ??= []).push(a);
        return acc;
    }, {});

    // Build the calendar grid — Monday-first, padded to full weeks.
    const [year, monthNum] = month.split('-').map(Number);
    const firstOfMonth = new Date(year, monthNum - 1, 1);
    const daysInMonth = new Date(year, monthNum, 0).getDate();
    const leadingBlanks = (firstOfMonth.getDay() + 6) % 7; // Mon=0 ... Sun=6
    const cells: (string | null)[] = [
        ...Array(leadingBlanks).fill(null),
        ...Array.from({ length: daysInMonth }, (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`),
    ];
    while (cells.length % 7 !== 0) cells.push(null);

    const todayStr = new Date().toISOString().slice(0, 10);
    const monthLabel = firstOfMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    const selectedRows = selectedDate ? byDate[selectedDate] ?? [] : [];

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

            <Card>
                <div className="flex items-center justify-between mb-3">
                    <Link href={`/activities?month=${prevMonth}`} className="rounded-full bg-slate-100 text-slate-600 text-sm font-bold px-3 py-1.5">
                        ‹
                    </Link>
                    <div className="font-extrabold text-brand-dark">{monthLabel}</div>
                    <Link href={`/activities?month=${nextMonth}`} className="rounded-full bg-slate-100 text-slate-600 text-sm font-bold px-3 py-1.5">
                        ›
                    </Link>
                </div>

                <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-slate-400 mb-1">
                    {WEEKDAYS.map((d) => <div key={d}>{d}</div>)}
                </div>

                <div className="grid grid-cols-7 gap-1">
                    {cells.map((dateStr, i) => {
                        if (!dateStr) return <div key={i} />;
                        const dayNum = Number(dateStr.slice(-2));
                        const rows = byDate[dateStr] ?? [];
                        const isToday = dateStr === todayStr;

                        return (
                            <button
                                key={dateStr}
                                onClick={() => setSelectedDate(dateStr)}
                                className={`aspect-square rounded-lg p-1 flex flex-col items-center justify-start text-xs border ${
                                    isToday ? 'border-brand bg-brand/5' : 'border-slate-100'
                                } ${selectedDate === dateStr ? 'ring-2 ring-brand' : ''}`}
                            >
                                <span className={`font-semibold ${isToday ? 'text-brand' : 'text-slate-600'}`}>{dayNum}</span>
                                {rows.length > 0 && (
                                    <span className="mt-0.5 h-1.5 w-1.5 rounded-full bg-brand" title={`${rows.length} activity(ies)`} />
                                )}
                            </button>
                        );
                    })}
                </div>
            </Card>

            {selectedDate && (
                <Card
                    title={new Date(selectedDate).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })}
                    className="mt-4"
                    action={
                        <button onClick={() => openAddFor(selectedDate)} className="text-sm font-semibold text-brand">
                            + Add
                        </button>
                    }
                >
                    {selectedRows.length === 0 && <p className="text-sm text-slate-400">Nothing logged for this day.</p>}
                    <ul className="space-y-1">
                        {selectedRows.map((a) => (
                            <li key={a.id} className="text-sm">
                                <b className="text-brand-dark">{a.start_time && `${a.start_time} · `}{a.title}</b>
                                {a.description && <span className="text-slate-500"> — {a.description}</span>}
                                <div className="text-xs text-slate-400">Logged by {a.user}</div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

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
