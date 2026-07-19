import { Head, Link, usePage } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import StatusPill from '../components/StatusPill';
import { ChecklistItem, SharedProps } from '../types';

interface Props {
    stats: { membersInToday: number; membersScheduled: number; animalsNeedingChecks: number };
    welfareAlerts: { id: number; name: string; species: string; welfare_status: string }[];
    checklist: ChecklistItem[];
}

function greeting() {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
}

export default function Dashboard({ stats, welfareAlerts, checklist }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const done = checklist.filter((c) => c.done).length;

    return (
        <AppShell title="Dashboard">
            <Head title="Dashboard" />

            <p className="text-2xl font-extrabold text-brand-dark mb-4">
                {greeting()}, {auth.user?.name?.split(' ')[0]} 👋
            </p>

            <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                <Card>
                    <div className="text-3xl font-extrabold text-brand">
                        {stats.membersInToday}
                        <span className="text-base text-slate-400 font-semibold">/{stats.membersScheduled}</span>
                    </div>
                    <div className="text-sm text-slate-500 font-medium">Members in today</div>
                </Card>
                <Card>
                    <div className={`text-3xl font-extrabold ${stats.animalsNeedingChecks > 0 ? 'text-status-amber' : 'text-status-green'}`}>
                        {stats.animalsNeedingChecks}
                    </div>
                    <div className="text-sm text-slate-500 font-medium">Animals awaiting checks</div>
                </Card>
                <Card className="col-span-2 md:col-span-1">
                    <div className="text-3xl font-extrabold text-brand-dark">
                        {done}
                        <span className="text-base text-slate-400 font-semibold">/{checklist.length}</span>
                    </div>
                    <div className="text-sm text-slate-500 font-medium">Today's checklist done</div>
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
        </AppShell>
    );
}
