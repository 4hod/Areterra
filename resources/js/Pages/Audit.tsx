import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import ModuleHero from '../components/ModuleHero';

interface Finding {
    severity: 'critical' | 'warning' | 'info';
    message: string;
    link: string;
}

const SEVERITY: Record<string, { dot: string; label: string }> = {
    critical: { dot: 'bg-status-red', label: 'Critical' },
    warning: { dot: 'bg-status-amber', label: 'Warning' },
    info: { dot: 'bg-brand', label: 'Info' },
};

function findingGroup(finding: Finding) {
    const value = `${finding.message} ${finding.link}`.toLowerCase();
    if (value.includes('animal') || value.includes('welfare') || value.includes('vet')) return 'Animal records';
    if (value.includes('member') || value.includes('review') || value.includes('session')) return 'Member records';
    if (value.includes('supervision') || value.includes('staff')) return 'Team records';
    if (value.includes('document') || value.includes('policy') || value.includes('compliance')) return 'Documents & compliance';
    if (value.includes('referral')) return 'Referrals';
    return 'Other checks';
}

export default function Audit({ findings, counts }: { findings: Finding[]; counts: Record<string, number> }) {
    const [checkedAt] = useState(() => new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));
    const [openGroup, setOpenGroup] = useState<string | null>(null);
    const groups = useMemo(() => Object.entries(findings.reduce<Record<string, Finding[]>>((result, finding) => {
        (result[findingGroup(finding)] ??= []).push(finding);
        return result;
    }, {})), [findings]);

    return (
        <AppShell title="System Audit">
            <Head title="System Audit" />
            <ModuleHero eyebrow="Quality assurance" title="Audit centre" description="Review checks, evidence and actions across the service." icon="🔍" tone="slate" />

            <div className="flex items-center justify-between mb-3">
                <span className="text-xs text-slate-400">Last checked {checkedAt} — checks run live on every load.</span>
                <button
                    onClick={() => router.reload()}
                    className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2"
                >
                    ↻ Refresh
                </button>
            </div>

            <div className="grid grid-cols-3 gap-3 mb-4">
                <Card>
                    <div className="text-2xl font-extrabold text-status-red">{counts.critical}</div>
                    <div className="text-xs text-slate-500 font-medium">Critical</div>
                </Card>
                <Card>
                    <div className="text-2xl font-extrabold text-status-amber">{counts.warning}</div>
                    <div className="text-xs text-slate-500 font-medium">Warnings</div>
                </Card>
                <Card>
                    <div className="text-2xl font-extrabold text-brand">{counts.info}</div>
                    <div className="text-xs text-slate-500 font-medium">Info</div>
                </Card>
            </div>

            {findings.length === 0 && (
                <Card className="text-center border-l-4 border-l-status-green">
                    <div className="text-4xl mb-1">✨</div>
                    <div className="font-bold text-brand-dark">All checks pass — nothing needs attention.</div>
                </Card>
            )}

            <div className="audit-groups-4a">
                {groups.map(([group, rows]) => {
                    const critical = rows.filter((row) => row.severity === 'critical').length;
                    const warning = rows.filter((row) => row.severity === 'warning').length;
                    const open = openGroup === group;
                    return <section key={group} className={open ? 'is-open' : ''}>
                        <button type="button" onClick={() => setOpenGroup(open ? null : group)} aria-expanded={open}>
                            <span className={critical ? 'is-critical' : warning ? 'is-warning' : 'is-info'}>{critical ? '!' : warning ? '•' : 'i'}</span>
                            <div><h2>{group}</h2><p>{critical ? `${critical} critical` : ''}{critical && warning ? ' · ' : ''}{warning ? `${warning} warning${warning === 1 ? '' : 's'}` : ''}{!critical && !warning ? `${rows.length} item${rows.length === 1 ? '' : 's'} to review` : ''}</p></div>
                            <strong>{rows.length}</strong><i>{open ? '−' : '+'}</i>
                        </button>
                        {open && <div>{rows.map((finding, index) => <Link key={index} href={finding.link}><span className={`audit-finding-dot-4a ${SEVERITY[finding.severity].dot}`} /><b>{finding.message}</b><em>View →</em></Link>)}</div>}
                    </section>;
                })}
            </div>

            <p className="mt-4 text-xs text-slate-400">
                Checks: member reviews, vet records, welfare statuses, session activity, supervisions, document
                read-confirmations, pending referrals, overdue compliance items.
            </p>
        </AppShell>
    );
}
