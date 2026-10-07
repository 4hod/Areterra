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
        impactEvidence: number;
        membersWithEvidence: number;
        averageEngagement: number;
        averageIndependence: number;
    };
    tagSummary: Record<string, number>;
}

export default function Reports({ from, to, impact, tagSummary }: Props) {
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
    ];
    const evidenceCoverage = impact.membersServed ? Math.round((impact.membersWithEvidence / impact.membersServed) * 100) : 0;
    const attendancePerMember = impact.membersServed ? (impact.attendances / impact.membersServed).toFixed(1) : '0';

    return (
        <AppShell title="Reports">
            <Head title="Reports" />
            <ModuleHero eyebrow="Service intelligence" title="Reports" description="Turn day-to-day records into useful insight and evidence." icon="📈" tone="blue" />

            <div className="flex flex-wrap items-center gap-2 mb-4">
                <input type="date" value={from} onChange={(e) => setRange(e.target.value, to)} className="rounded-lg border border-slate-300 px-3 bg-white" />
                <span className="text-slate-400">→</span>
                <input type="date" value={to} onChange={(e) => setRange(from, e.target.value)} className="rounded-lg border border-slate-300 px-3 bg-white" />
            </div>

            <section className="report-overview-4a">
                <div><span>Period</span><strong>{new Date(from).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} – {new Date(to).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</strong></div>
                <div><span>Attendance per member</span><strong>{attendancePerMember}</strong></div>
                <div><span>Members with outcome evidence</span><strong>{impact.membersWithEvidence} <small>({evidenceCoverage}%)</small></strong></div>
            </section>

            <Card title="Delivery at a glance" className="mb-4 report-delivery-4a">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    {tiles.map(([label, value, icon]) => (
                        <div key={label} className="rounded-lg bg-slate-50 p-3 text-center">
                            <div className="text-2xl" aria-hidden>{icon}</div>
                            <div className="text-2xl font-extrabold text-brand-dark">{value}</div>
                            <div className="text-xs text-slate-500 font-medium">{label}</div>
                        </div>
                    ))}
                </div>
            </Card>

            <section className="report-evidence-4a">
                <header><div><span>Outcome evidence</span><h2>What changed for members</h2></div><strong>{impact.impactEvidence}<small> evidence records</small></strong></header>
                <div className="report-evidence-stats-4a"><article><span>Average engagement</span><b>{impact.averageEngagement || '—'}<small>/5</small></b></article><article><span>Average independence</span><b>{impact.averageIndependence || '—'}<small>/5</small></b></article></div>
                <div className="report-tags-4a">{Object.keys(tagSummary).length ? Object.entries(tagSummary).map(([tag, count]) => <span key={tag}>{tag.replace('-', ' ')} <b>{count}</b></span>) : <p>No outcome tags have been recorded for this period yet.</p>}</div>
            </section>

            <div className="report-actions-4a"><button onClick={() => window.print()}>🖨 Print impact report</button><a href={`/reports/activities.csv${range}`}>⬇ Export period activity</a></div>

            <Card title="Data exports" className="report-exports-4a">
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
                </div>
                <p className="text-xs text-slate-400 mt-2">Activities respect the date range above.</p>
            </Card>
        </AppShell>
    );
}
