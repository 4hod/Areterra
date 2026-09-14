import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface CalEvent {
    date: string;
    type: 'activity' | 'leave';
    title: string;
    id: number;
    status?: string;
    start_time?: string | null;
    description?: string | null;
}

interface PendingLeave {
    id: number;
    user: string;
    leave_type: string;
    start_date: string;
    end_date: string;
    days: number;
    reason: string | null;
}

interface Props {
    month: string;
    prevMonth: string;
    nextMonth: string;
    events: CalEvent[];
    canManageLeave: boolean;
    pendingLeave: PendingLeave[];
}

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export default function Calendar({ month, prevMonth, nextMonth, events, canManageLeave, pendingLeave }: Props) {
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [reviewing, setReviewing] = useState(false);
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        activity_date: new Date().toISOString().slice(0, 10),
        start_time: '',
        description: '',
    });

    const byDate = events.reduce<Record<string, CalEvent[]>>((acc, e) => {
        (acc[e.date] ??= []).push(e);
        return acc;
    }, {});

    const [year, monthNum] = month.split('-').map(Number);
    const firstOfMonth = new Date(year, monthNum - 1, 1);
    const daysInMonth = new Date(year, monthNum, 0).getDate();
    const leadingBlanks = (firstOfMonth.getDay() + 6) % 7;
    const cells: (string | null)[] = [
        ...Array(leadingBlanks).fill(null),
        ...Array.from({ length: daysInMonth }, (_, i) => `${month}-${String(i + 1).padStart(2, '0')}`),
    ];
    while (cells.length % 7 !== 0) cells.push(null);

    const todayStr = new Date().toISOString().slice(0, 10);
    const monthLabel = firstOfMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    const selectedEvents = selectedDate ? byDate[selectedDate] ?? [] : [];

    function review(id: number, status: 'approved' | 'declined') {
        router.put(`/leave/${id}/review`, { status }, { preserveScroll: true });
    }

    function openAdd(date?: string) {
        if (date) setData('activity_date', date);
        setAdding(true);
    }

    function submitActivity(e: FormEvent) {
        e.preventDefault();
        post('/activities', { onSuccess: () => { setAdding(false); reset(); } });
    }

    return (
        <AppShell title="Calendar">
            <Head title="Calendar" />
            <ModuleHero eyebrow="Plan and deliver" title="Calendar & activities" description="Plan the weekly programme, add activities and see leave in one calendar." icon="🗓️" tone="purple" />

            <div className="flex gap-2 mb-4 flex-wrap">
                <button onClick={() => openAdd(selectedDate ?? undefined)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5">+ Add activity</button>
                <a href={`/weekly-planner/print?week=${selectedDate ?? todayStr}`} target="_blank" rel="noreferrer" className="rounded-full bg-slate-100 text-brand-dark font-semibold text-sm px-5 py-2.5">🖨 Print week</a>
            </div>

            {canManageLeave && pendingLeave.length > 0 && (
                <button onClick={() => setReviewing(true)} className="rounded-full bg-amber-100 text-amber-800 font-semibold text-sm px-5 py-2.5 mb-4">
                    ⏳ {pendingLeave.length} pending leave request{pendingLeave.length !== 1 && 's'} →
                </button>
            )}

            <Card>
                <div className="flex items-center justify-between mb-3">
                    <Link href={`/calendar?month=${prevMonth}`} className="rounded-full bg-slate-100 text-slate-600 text-sm font-bold px-3 py-1.5">‹</Link>
                    <div className="font-extrabold text-brand-dark">{monthLabel}</div>
                    <Link href={`/calendar?month=${nextMonth}`} className="rounded-full bg-slate-100 text-slate-600 text-sm font-bold px-3 py-1.5">›</Link>
                </div>

                <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-slate-400 mb-1">
                    {WEEKDAYS.map((d) => <div key={d}>{d}</div>)}
                </div>

                <div className="grid grid-cols-7 gap-1">
                    {cells.map((dateStr, i) => {
                        if (!dateStr) return <div key={i} />;
                        const dayNum = Number(dateStr.slice(-2));
                        const rows = byDate[dateStr] ?? [];
                        const hasActivity = rows.some((r) => r.type === 'activity');
                        const hasLeave = rows.some((r) => r.type === 'leave');
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
                                <div className="flex gap-0.5 mt-0.5">
                                    {hasActivity && <span className="h-1.5 w-1.5 rounded-full bg-brand" title="Activity" />}
                                    {hasLeave && <span className="h-1.5 w-1.5 rounded-full bg-amber-500" title="Leave" />}
                                </div>
                            </button>
                        );
                    })}
                </div>

                <div className="flex gap-4 mt-3 text-xs text-slate-500">
                    <span><span className="inline-block h-2 w-2 rounded-full bg-brand mr-1" />Activity</span>
                    <span><span className="inline-block h-2 w-2 rounded-full bg-amber-500 mr-1" />Leave</span>
                </div>
            </Card>

            {selectedDate && (
                <Card title={new Date(selectedDate).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })} className="mt-4">
                    {selectedEvents.length === 0 && <p className="text-sm text-slate-400">Nothing on this day.</p>}
                    <ul className="space-y-1">
                        {selectedEvents.map((e, i) => (
                            <li key={i} className="text-sm flex items-center gap-2">
                                <span>{e.type === 'activity' ? '📅' : '🏖️'}</span>
                                <span className="text-brand-dark font-medium">{e.start_time && `${e.start_time} · `}{e.title}</span>
                                {e.status && <span className="text-xs text-slate-400">({e.status})</span>}
                                {e.description && <span className="text-xs text-slate-500">{e.description}</span>}
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Modal open={reviewing} title="Pending leave requests" onClose={() => setReviewing(false)}>
                <div className="space-y-3">
                    {pendingLeave.map((l) => (
                        <div key={l.id} className="border border-slate-100 rounded-lg p-3">
                            <div className="font-bold text-sm text-brand-dark">{l.user} — {l.leave_type}</div>
                            <div className="text-xs text-slate-500">
                                {new Date(l.start_date).toLocaleDateString('en-GB')} – {new Date(l.end_date).toLocaleDateString('en-GB')} ({l.days} days)
                            </div>
                            {l.reason && <div className="text-sm text-slate-500 mt-1">{l.reason}</div>}
                            <div className="flex gap-1.5 mt-2">
                                <button onClick={() => review(l.id, 'approved')} className="rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5">✓ Approve</button>
                                <button onClick={() => review(l.id, 'declined')} className="rounded-full bg-red-100 text-red-700 text-xs font-bold px-3 py-1.5">✕ Decline</button>
                            </div>
                        </div>
                    ))}
                    {pendingLeave.length === 0 && <p className="text-sm text-slate-400">Nothing pending.</p>}
                </div>
            </Modal>

            <Modal open={adding} title="Add activity" onClose={() => setAdding(false)}>
                <form onSubmit={submitActivity} className="space-y-3">
                    <label className="block text-sm font-medium">Activity<input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required /></label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">Date<input type="date" value={data.activity_date} onChange={(e) => setData('activity_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required /></label>
                        <label className="block text-sm font-medium">Start time<input type="time" value={data.start_time} onChange={(e) => setData('start_time', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label>
                    </div>
                    <label className="block text-sm font-medium">Notes<textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} /></label>
                    <button disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">Add to calendar</button>
                </form>
            </Modal>
        </AppShell>
    );
}
