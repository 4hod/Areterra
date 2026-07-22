import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import EmptyState from '../components/EmptyState';
import BarChart from '../components/BarChart';
import ChartCard from '../components/ChartCard';
import Sparkline from '../components/Sparkline';
import StatTile from '../components/StatTile';
import StatusPill from '../components/StatusPill';
import { ChecklistItem, SharedProps } from '../types';

interface Props {
    orgIsEmpty: boolean;
    stats: { membersInToday: number; membersScheduled: number; animalsNeedingChecks: number; attendanceTrend: number[] };
    welfareAlerts: { id: number; name: string; species: string; welfare_status: string }[];
    checklist: ChecklistItem[];
    banner: string | null;
    staffAvatars: string[];
    myShift: { clock_in: string } | null;
    leaveBalance: { entitlement: number; taken: number; remaining: number };
    announcements: { id: number; title: string; author: string; created_at: string; read: boolean }[];
    notifications: { id: string; title: string; body: string; url: string; read: boolean; created_at: string }[];
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

const QUICK_ACTIONS = [
    { href: '/register', label: 'Morning Register', icon: '📋', color: 'cat-people' },
    { href: '/animals', label: 'Welfare Checks', icon: '🦜', color: 'cat-ops' },
    { href: '/end-of-day', label: 'Log End of Day', icon: '🌙', color: 'cat-staff' },
] as const;

const QUICK_ACTION_BG: Record<string, string> = {
    'cat-people': 'bg-cat-people/10 text-cat-people',
    'cat-ops': 'bg-cat-ops/10 text-cat-ops',
    'cat-staff': 'bg-cat-staff/10 text-cat-staff',
};

export default function Dashboard({ orgIsEmpty, stats, welfareAlerts, checklist, banner, staffAvatars, myShift, leaveBalance, announcements, notifications }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const done = checklist.filter((c) => c.done).length;

    if (orgIsEmpty) {
        return (
            <AppShell title="Dashboard">
                <Head title="Dashboard" />
                <div className="flex flex-col items-center justify-center text-center py-20 max-w-md mx-auto">
                    <span className="text-5xl mb-4" aria-hidden>🦜🐰🐹</span>
                    <h2 className="text-2xl font-bold text-brand-dark mb-2">
                        Welcome, {auth.user?.name?.split(' ')[0]}!
                    </h2>
                    <p className="text-sm text-ink/50 mb-6">
                        Your hub is set up but doesn't have any members or animals yet. Add your first ones to get
                        started — everything else (registers, welfare checks, end-of-day records) will come to life
                        from there.
                    </p>
                    <div className="flex gap-2">
                        <Link href="/members" className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5">
                            + Add a member
                        </Link>
                        <Link href="/animals" className="rounded-full bg-brand-dark text-white font-semibold text-sm px-5 py-2.5">
                            + Add an animal
                        </Link>
                    </div>
                </div>
            </AppShell>
        );
    }

    return (
        <AppShell title="Dashboard">
            <Head title="Dashboard" />

            {banner && (
                <div className="rounded-card bg-accent/20 border border-accent text-brand-dark font-semibold text-sm px-4 py-3 mb-4">
                    📣 {banner}
                </div>
            )}

            <p className="text-2xl font-bold text-brand-dark">
                {greeting()}, {auth.user?.name?.split(' ')[0]} 👋
            </p>
            <p className="text-sm text-ink/45 font-medium mb-4">
                {new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
            </p>

            {/* Staff avatars row */}
            {staffAvatars.length > 0 && (
                <div className="flex -space-x-2 mb-5">
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
            )}

            {/* Quick actions — real button-cards, not tiny pills */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                {QUICK_ACTIONS.map((a) => (
                    <Link
                        key={a.href}
                        href={a.href}
                        className="flex items-center gap-3 rounded-card bg-white border border-black/[0.06] px-4 py-3.5 hover:border-black/[0.12] transition-colors"
                        style={{ boxShadow: 'var(--shadow-card)' }}
                    >
                        <span className={`h-10 w-10 rounded-lg flex items-center justify-center text-lg shrink-0 ${QUICK_ACTION_BG[a.color]}`}>
                            {a.icon}
                        </span>
                        <span className="font-semibold text-sm text-ink/80">{a.label}</span>
                    </Link>
                ))}
            </div>

            <div className="grid lg:grid-cols-[1fr_320px] gap-5 items-start">
                {/* ── Main column ── */}
                <div className="space-y-5 min-w-0">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <StatTile
                            icon="👥"
                            color="var(--color-brand)"
                            value={<><CountUp value={stats.membersInToday} />/{stats.membersScheduled}</>}
                            label="Members in today"
                        >
                            <div className="mt-2">
                                <Sparkline values={stats.attendanceTrend} color="rgba(255,255,255,0.85)" />
                            </div>
                        </StatTile>
                        <StatTile
                            icon="🦜"
                            color={stats.animalsNeedingChecks > 0 ? 'var(--color-cat-ops)' : 'var(--color-status-green)'}
                            value={<CountUp value={stats.animalsNeedingChecks} />}
                            label="Animals awaiting checks"
                        />
                        <div className="col-span-2 md:col-span-1">
                            <StatTile
                                icon="✅"
                                color="var(--color-brand-dark)"
                                value={<><CountUp value={done} />/{checklist.length}</>}
                                label="Today's checklist done"
                            />
                        </div>
                    </div>

                    <div className="grid md:grid-cols-2 gap-3">
                        <Card title="Weekly attendance">
                            <BarChart values={stats.attendanceTrend} />
                        </Card>
                        <ChartCard
                            title="Attendance this week"
                            value={stats.attendanceTrend.reduce((sum, v) => sum + v, 0)}
                            values={stats.attendanceTrend}
                            color="var(--color-brand)"
                        />
                    </div>

                    <div className="grid md:grid-cols-2 gap-3">
                        <Card title="⏱️ My shift">
                            {myShift ? (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm">
                                        Clocked in at <b className="text-brand-dark">{myShift.clock_in}</b>
                                    </span>
                                    <Link href="/timeclock" className="rounded-full bg-status-red text-white text-xs font-bold px-4 py-2">
                                        Clock out
                                    </Link>
                                </div>
                            ) : (
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-ink/45">Not clocked in</span>
                                    <button
                                        onClick={() => router.post('/timeclock/in')}
                                        className="rounded-full bg-status-green text-white text-xs font-bold px-4 py-2"
                                    >
                                        Clock in
                                    </button>
                                </div>
                            )}
                        </Card>

                        <Card title="🌴 Leave balance">
                            <div className="flex items-center justify-between">
                                <span className="text-sm">
                                    <b className="text-brand-dark">{leaveBalance.remaining}</b> of {leaveBalance.entitlement} days left
                                    <span className="text-ink/40"> · {leaveBalance.taken} taken</span>
                                </span>
                                <Link href="/leave" className="rounded-full bg-brand text-white text-xs font-bold px-4 py-2">
                                    Request leave
                                </Link>
                            </div>
                        </Card>
                    </div>

                    {welfareAlerts.length > 0 && (
                        <Card title="Welfare alerts" className="border-l-4 border-l-status-amber">
                            <ul className="divide-y divide-black/[0.04]">
                                {welfareAlerts.map((a) => (
                                    <li key={a.id} className="py-2 flex items-center justify-between">
                                        <Link href={`/animals/${a.id}`} className="font-medium text-brand-dark">
                                            {a.name} <span className="text-ink/40 text-sm">({a.species})</span>
                                        </Link>
                                        <StatusPill status={a.welfare_status} />
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}

                    <Card title="Today" action={<Link href="/today" className="text-sm font-semibold text-brand">View all →</Link>}>
                        <ul className="space-y-2">
                            {checklist.map((item) => (
                                <li key={item.key} className="flex items-center gap-3">
                                    <span
                                        className={`h-6 w-6 rounded-full flex items-center justify-center text-xs font-bold text-white ${
                                            item.done ? 'bg-status-green' : 'bg-ink/15'
                                        }`}
                                    >
                                        {item.done ? '✓' : ''}
                                    </span>
                                    <span className={item.done ? 'text-ink/35 line-through' : 'font-medium text-ink/75'}>
                                        {item.label}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>

                {/* ── Right rail ── */}
                <div className="space-y-5 min-w-0">
                    <Card title="Announcements" action={<Link href="/announcements" className="text-sm font-semibold text-brand">All →</Link>}>
                        {announcements.length === 0 ? (
                            <EmptyState icon="📢" text="No announcements yet." />
                        ) : (
                            <ul className="divide-y divide-black/[0.04]">
                                {announcements.map((a) => (
                                    <li key={a.id} className="py-2">
                                        <Link href="/announcements" className={`text-sm font-semibold ${a.read ? 'text-ink/35' : 'text-brand-dark'}`}>
                                            {!a.read && <span className="text-brand mr-1">●</span>}
                                            {a.title}
                                        </Link>
                                        <div className="text-xs text-ink/40">
                                            {a.author} · {new Date(a.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card title="Notifications" action={<Link href="/notifications" className="text-sm font-semibold text-brand">Settings →</Link>}>
                        {notifications.length === 0 ? (
                            <EmptyState icon="🔔" text="Nothing to show yet." />
                        ) : (
                            <ul className="divide-y divide-black/[0.04]">
                                {notifications.map((n) => (
                                    <li key={n.id} className="py-2">
                                        <Link
                                            href={n.url}
                                            onClick={() => !n.read && router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true })}
                                            className={`text-sm font-semibold block ${n.read ? 'text-ink/35' : 'text-brand-dark'}`}
                                        >
                                            {!n.read && <span className="text-brand mr-1">●</span>}
                                            {n.title}
                                        </Link>
                                        <div className="text-xs text-ink/40">{n.body} · {n.created_at}</div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}
