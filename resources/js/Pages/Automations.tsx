import { Head, router } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import ModuleHero from '../components/ModuleHero';

interface Rule { id: number; name: string; trigger: string; action: string; active: boolean; last_run_at: string | null }
interface Run { id: number; rule: string; status: string; summary: string | null; ran_at: string }

export default function Automations({ rules, runs }: { rules: Rule[]; runs: Run[] }) {
    return <AppShell title="Automations">
        <Head title="Automations" />
        <ModuleHero eyebrow="Areterra rules engine" title="Automations" description="Reliable recipes that turn everyday records into useful follow-up work." icon="⚡" tone="amber" />
        <div className="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div><b className="text-brand-dark">The Hub is working in the background</b><p className="text-sm text-slate-600">Rules are also scanned hourly. Running a scan twice will not duplicate tasks.</p></div>
            <button onClick={() => router.post('/automations/run')} className="shrink-0 rounded-xl bg-brand px-4 py-3 text-sm font-bold text-white">Run now</button>
        </div>
        <div className="grid gap-4 lg:grid-cols-2">
            <Card title="Live recipes"><div className="space-y-3">{rules.map(rule => <div key={rule.id} className="rounded-xl border border-slate-200 p-4">
                <div className="flex items-start justify-between gap-3"><div><b className="text-brand-dark">{rule.name}</b><p className="mt-1 text-xs text-slate-500">When: {rule.trigger.replace(/_/g, ' ')} · Then: {rule.action.replace(/_/g, ' ')}</p></div>
                <button onClick={() => router.put(`/automations/${rule.id}`, { active: !rule.active })} className={`rounded-full px-3 py-1.5 text-xs font-bold ${rule.active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'}`}>{rule.active ? '● Live' : 'Paused'}</button></div>
                {rule.last_run_at && <p className="mt-2 text-xs text-slate-400">Last ran {new Date(rule.last_run_at).toLocaleString('en-GB')}</p>}
            </div>)}</div></Card>
            <Card title="Recent activity"><div className="space-y-3">{runs.length === 0 && <p className="text-sm text-slate-500">No automation runs yet.</p>}{runs.map(run => <div key={run.id} className="border-b border-slate-100 pb-3 last:border-0">
                <div className="flex justify-between gap-3"><b className="text-sm text-brand-dark">{run.rule}</b><span className={`text-xs font-bold ${run.status === 'completed' ? 'text-emerald-700' : 'text-red-700'}`}>{run.status}</span></div>
                <p className="mt-1 text-sm text-slate-600">{run.summary}</p><p className="mt-1 text-xs text-slate-400">{new Date(run.ran_at).toLocaleString('en-GB')}</p>
            </div>)}</div></Card>
        </div>
    </AppShell>;
}
