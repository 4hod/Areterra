import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import StatusPill from '../components/StatusPill';
import { ChecklistItem, SharedProps } from '../types';

interface Props {
    stats: { membersInToday: number; membersScheduled: number; animalsNeedingChecks: number };
    welfareAlerts: { id: number; name: string; species: string; welfare_status: string }[];
    checklist: ChecklistItem[];
    banner: string | null;
    staffAvatars: string[];
    myShift: { clock_in: string } | null;
    leaveBalance: { entitlement: number; taken: number; remaining: number };
    announcements: { id: number; title: string; author: string; created_at: string; read: boolean }[];
}

function greeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
}

const AVATAR_COLOURS = ['bg-brand', 'bg-brand-dark', 'bg-emerald-600', 'bg-purple-600', 'bg-rose-500', 'bg-amber-500'];

// Count-up animation for the stat tiles (SPEC checklist).
function CountUp({ value }: { value: number }) {
    const [display, setDisplay] = useState(0);
    useEffect(() => {
        if (value === 0) return setDisplay(0);
        let frame = 0;
        const frames = 20;
        const timer = setInterval(() => {
            frame++;
            setDisplay(Math.round((value * frame) / frames));
            if (frame >= frames) clearInterval(timer);
        }, 30);
        return () => clearInterval(timer);
    }, [value]);
    return <>{display}</>;
}

export default function Dashboard({ stats, welfareAlerts, checklist, banner, staffAvatars, myShift, leaveBalance, announcements }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const done = checklist.filter((c) => c.done).length;

    return (
        <AppShell title="Dashboard">
            <Head title="Dashboard" />

            {banner && (
                <div className="rounded-card bg-accent/20 border border-accent text-brand-dark font-semibold text-sm px-4 py-3 mb-4">
                    📣 {banner}
                </div>
            )}

            <p className="text-2xl font-extrabold text-brand-dark">
                {greeting()}, {auth.user?.name?.split(' ')[0]} 👋
            </p>
            <p className="text-sm text-slate-500 font-medium mb-3">
                {new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
            </p>

            {/* Staff avatars row */}
            <div className="flex -space-x-2 mb-4">
                {staffAvatars.map((name, i) => (
                    <div
                        key={i}
                        title={name}
                        className={`h-9 w-9 rounded-full ${AVATAR_COLOURS[i % AVATAR_COLOURS.length]} text-white text-sm font-bold flex items-center justify-center ring-2 ring-white`}
                    >
                        {name.charAt(0)}
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                <Card>
                    <div className="text-3xl font-extrabold text-brand">
                        <CountUp value={stats.membersInToday} />
                        <span className="text-base text-slate-400 font-semibold">/{stats.membersScheduled}</span>
                    </div>
                    <div className="text-sm text-slate-500 font-medium">Members in today</div>
                </Card>
                <Card>
                    <div className={`text-3xl font-extrabold ${stats.animalsNeedingChecks > 0 ? 'text-status-amber' : 'text-status-green'}`}>
                        <CountUp value={stats.animalsNeedingChecks} />
                    </div>
                    <div className="text-sm text-slate-500 font-medium">Animals awaiting checks</div>
                </Card>
                <Card className="col-span-2 md:col-span-1">
                    <div className="text-3xl font-extrabold text-brand-dark">
                        <CountUp value={done} />
                        <span className="text-base text-slate-400 font-semibold">/{checklist.length}</span>
                    </div>
                    <div className="text-sm text-slate-500 font-medium">Today's checklist done</div>
                </Card>
            </div>

            <div className="grid md:grid-cols-2 gap-3 mb-4">
                {/* My Shift widget */}
                <Card title="⏱️ My shift">
                    {myShift ? (
                        <div className="flex items-center justify-between">
                            <span className="text-sm">
                                Clocked in at <b className="text-brand-dark">{myShift.clock_in}</b>
                            </span>
                            <Link href="/timeclock" className="rounded-full bg-red-600 text-white text-xs font-bold px-4 py-2">
                                Clock out
                            </Link>
                        </div>
                    ) : (
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-slate-500">Not clocked in</span>
                            <button
                                onClick={() => router.post('/timeclock/in')}
                                className="rounded-full bg-status-green text-white text-xs font-bold px-4 py-2"
                            >
                                Clock in
                            </button>
                        </div>
                    )}
                </Card>

                {/* Leave balance widget */}
                <Card title="🌴 Leave balance">
                    <div className="flex items-center justify-between">
                        <span className="text-sm">
                            <b className="text-brand-dark">{leaveBalance.remaining}</b> of {leaveBalance.entitlement} days left
                            <span className="text-slate-400"> · {leaveBalance.taken} taken</span>
                        </span>
                        <Link href="/leave" className="rounded-full bg-brand text-white text-xs font-bold px-4 py-2">
                            Request leave
                        </Link>
                    </div>
                </Card>
            </div>

            <div className="flex flex-wrap gap-2 mb-4">
                <Link href="/register" className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5">
                    📋 Morning Register
                </Link>
                <Link href="/animals" className="rounded-full bg-brand-dark text-white font-semibold text-sm px-4 py-2.5">
                    🦜 Welfare Checks
                </Link>
                <Link href="/end-of-day" className="rounded-full bg-slate-700 text-white font-semibold text-sm px-4 py-2.5">
                    🌙 Log End of Day
                </Link>
            </div>

            {welfareAlerts.length > 0 && (
                <Card title="Welfare alerts" className="mb-4 border-l-4 border-l-status-amber">
                    <ul className="divide-y divide-slate-100">
                        {welfareAlerts.map((a) => (
                            <li key={a.id} className="py-2 flex items-center justify-between">
                                <Link href={`/animals/${a.id}`} className="font-medium text-brand-dark">
                                    {a.name} <span className="text-slate-400 text-sm">({a.species})</span>
                                </Link>
                                <StatusPill status={a.welfare_status} />
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <div className="grid md:grid-cols-2 gap-3">
                <Card title="Today" action={<Link href="/today" className="text-sm font-semibold text-brand">View all →</Link>}>
                    <ul className="space-y-2">
                        {checklist.map((item) => (
                            <li key={item.key} className="flex items-center gap-3">
                                <span
                                    className={`h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold text-white ${
                                        item.done ? 'bg-status-green' : 'bg-slate-300'
                                    }`}
                                >
                                    {item.done ? '✓' : ''}
                                </span>
                                <span className={item.done ? 'text-slate-400 line-through' : 'font-medium text-slate-700'}>
                                    {item.label}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title="Announcements" action={<Link href="/announcements" className="text-sm font-semibold text-brand">All →</Link>}>
                    {announcements.length === 0 && <p className="text-sm text-slate-400">No announcements yet.</p>}
                    <ul className="divide-y divide-slate-100">
                        {announcements.map((a) => (
                            <li key={a.id} className="py-2">
                                <Link href="/announcements" className={`text-sm font-semibold ${a.read ? 'text-slate-400' : 'text-brand-dark'}`}>
                                    {!a.read && <span className="text-brand mr-1">●</span>}
                                    {a.title}
                                </Link>
                                <div className="text-xs text-slate-400">
                                    {a.author} · {new Date(a.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </AppShell>
    );
}
