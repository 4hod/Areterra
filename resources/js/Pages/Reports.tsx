import { Head, router } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import ModuleHero from '../components/ModuleHero';

interface Props {
    from: string;
    to: string;
    impact: {
        membersServed: number;
        attendances: number;
        sessionsRecorded: number;
        welfareChecks: number;
        activities: number;
        staffHours: number;
    };
}

export default function Reports({ from, to, impact }: Props) {
    function setRange(f: string, t: string) {
        router.get('/reports', { from: f, to: t }, { preserveState: true });
    }

    const range = `?from=${from}&to=${to}`;

    const tiles: [string, number | string, string][] = [
        ['Members served', impact.membersServed, '👥'],
        ['Attendances', impact.attendances, '📋'],
        ['Session records', impact.sessionsRecorded, '🌙'],
        ['Welfare checks', impact.welfareChecks, '🦜'],
        ['Activities', impact.activities, '📅'],
        ['Staff hours', impact.staffHours, '⏱️'],
    ];

    return (
        <AppShell title="Reports">
            <Head title="Reports" />
            <ModuleHero eyebrow="Service intelligence" title="Reports" description="Turn day-to-day records into useful insight and evidence." icon="📈" tone="blue" />

            <div className="flex flex-wrap items-center gap-2 mb-4">
                <input type="date" value={from} onChange={(e) => setRange(e.target.value, to)} className="rounded-lg border border-slate-300 px-3 bg-white" />
                <span className="text-slate-400">→</span>
                <input type="date" value={to} onChange={(e) => setRange(from, e.target.value)} className="rounded-lg border border-slate-300 px-3 bg-white" />
            </div>

            <Card title="Impact report" className="mb-4">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    {tiles.map(([label, value, icon]) => (
                        <div key={label} className="rounded-lg bg-slate-50 p-3 text-center">
                            <div className="text-2xl" aria-hidden>{icon}</div>
                            <div className="text-2xl font-extrabold text-brand-dark">{value}</div>
                            <div className="text-xs text-slate-500 font-medium">{label}</div>
                        </div>
                    ))}
                </div>
                <button onClick={() => window.print()} className="mt-4 rounded-full bg-brand-dark text-white text-xs font-bold px-4 py-2">
                    🖨 Print impact report
                </button>
            </Card>

            <Card title="CSV exports">
                <div className="flex flex-wrap gap-2">
                    <a href="/reports/members.csv" className="rounded-full bg-brand text-white text-sm font-semibold px-4 py-2.5">
                        ⬇ Members
                    </a>
                    <a href="/reports/animals.csv" className="rounded-full bg-brand text-white text-sm font-semibold px-4 py-2.5">
                        ⬇ Animals
                    </a>
                    <a href={`/reports/activities.csv${range}`} className="rounded-full bg-brand text-white text-sm font-semibold px-4 py-2.5">
                        ⬇ Activities
                    </a>
                    <a href={`/reports/hours.csv${range}`} className="rounded-full bg-brand text-white text-sm font-semibold px-4 py-2.5">
                        ⬇ Staff hours
                    </a>
                </div>
                <p className="text-xs text-slate-400 mt-2">Activities and hours respect the date range above.</p>
            </Card>
        </AppShell>
    );
}
