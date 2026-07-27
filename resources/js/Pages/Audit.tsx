import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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

export default function Audit({ findings, counts }: { findings: Finding[]; counts: Record<string, number> }) {
    const [checkedAt] = useState(() => new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }));

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

            <div className="space-y-2">
                {findings.map((f, i) => (
                    <Link key={i} href={f.link} className="block">
                        <Card>
                            <div className="flex items-center gap-3">
                                <span className={`h-3 w-3 shrink-0 rounded-full ${SEVERITY[f.severity].dot}`} />
                                <span className="flex-1 text-sm font-medium">{f.message}</span>
                                <span className="text-brand text-sm font-bold">→</span>
                            </div>
                        </Card>
                    </Link>
                ))}
            </div>

            <p className="mt-4 text-xs text-slate-400">
                Checks: member reviews, vet records, welfare statuses, session activity, supervisions, document
                read-confirmations, pending referrals, overdue compliance items.
            </p>
        </AppShell>
    );
}
