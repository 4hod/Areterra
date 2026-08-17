import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface ActivityRow {
    id: number;
    title: string;
    start_time: string | null;
    description: string | null;
    user: string;
}

interface Day {
    date: string;
    label: string;
    short: string;
    activities: ActivityRow[];
}

interface Props {
    weekStart: string;
    weekEnd: string;
    prevWeek: string;
    nextWeek: string;
    days: Day[];
}

export default function WeeklyPlanner({ weekStart, weekEnd, prevWeek, nextWeek, days }: Props) {
    const [addingFor, setAddingFor] = useState<string | null>(null);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        activity_date: '',
        start_time: '',
        description: '',
    });

    function openAdd(date: string) {
        setData('activity_date', date);
        setAddingFor(date);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/activities', { onSuccess: () => { setAddingFor(null); reset(); } });
    }

    const rangeLabel = `${new Date(weekStart).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} – ${new Date(weekEnd).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`;
    const todayStr = new Date().toISOString().slice(0, 10);

    return (
        <AppShell title="Weekly Planner">
            <Head title="Weekly Planner" />
            <ModuleHero
                eyebrow="Programme delivery"
                title="Weekly Planner"
                description="Schedule the week ahead, print it for the wall, and look back at any past week."
                icon="🗓️"
                tone="purple"
            />

            <div className="flex items-center justify-between mb-4 gap-2 flex-wrap">
                <div className="flex items-center gap-2">
                    <Link href={`/weekly-planner?week=${prevWeek}`} className="rounded-full bg-ink/[0.06] text-ink/70 text-sm font-bold px-3 py-2">
                        ‹ Prev
                    </Link>
                    <div className="font-extrabold text-brand-dark">{rangeLabel}</div>
                    <Link href={`/weekly-planner?week=${nextWeek}`} className="rounded-full bg-ink/[0.06] text-ink/70 text-sm font-bold px-3 py-2">
                        Next ›
                    </Link>
                </div>
                <a
                    href={`/weekly-planner/print?week=${weekStart}`}
                    target="_blank"
                    rel="noreferrer"
                    className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5"
                >
                    🖨 Print this week
                </a>
            </div>

            <div className="grid md:grid-cols-2 xl:grid-cols-4 gap-3">
                {days.map((day) => (
                    <Card
                        key={day.date}
                        title={day.label}
                        className={day.date === todayStr ? 'border-2 border-brand' : ''}
                        action={
                            <button onClick={() => openAdd(day.date)} className="text-sm font-semibold text-brand">
                                + Add
                            </button>
                        }
                    >
                        <div className="text-xs text-ink/40 -mt-2 mb-2">{day.short}</div>
                        {day.activities.length === 0 && <p className="text-sm text-ink/35">Nothing planned yet.</p>}
                        <ul className="space-y-2">
                            {day.activities.map((a) => (
                                <li key={a.id} className="text-sm border-l-2 border-brand/30 pl-2">
                                    <div className="font-semibold text-brand-dark">
                                        {a.start_time && <span className="text-ink/40 font-normal">{a.start_time} · </span>}
                                        {a.title}
                                    </div>
                                    {a.description && <div className="text-ink/50 text-xs mt-0.5">{a.description}</div>}
                                    <div className="text-ink/30 text-[11px] mt-0.5">by {a.user}</div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                ))}
            </div>

            <Modal open={addingFor !== null} title={`Plan for ${addingFor ? new Date(addingFor).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }) : ''}`} onClose={() => setAddingFor(null)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Activity
                        <input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="e.g. Horticulture group, Animal handling session"
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            required
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Start time (optional)
                        <input
                            type="time"
                            value={data.start_time}
                            onChange={(e) => setData('start_time', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Notes (optional)
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add to plan
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
