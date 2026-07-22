import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';

interface IncidentRow {
    id: number;
    title: string;
    occurred_at: string;
    location: string | null;
    description: string;
    persons_involved: string | null;
    injury_details: string | null;
    severity: 'minor' | 'moderate' | 'serious' | 'critical';
    actions_taken: string | null;
    follow_up_required: boolean;
    status: 'open' | 'under-review' | 'closed';
    reported_by: string;
}

const SEVERITY_STYLE: Record<string, string> = {
    minor: 'bg-slate-100 text-slate-600',
    moderate: 'bg-amber-100 text-amber-800',
    serious: 'bg-orange-100 text-orange-800',
    critical: 'bg-red-100 text-red-700',
};

const STATUS_STYLE: Record<string, string> = {
    open: 'bg-red-100 text-red-700',
    'under-review': 'bg-amber-100 text-amber-800',
    closed: 'bg-emerald-100 text-emerald-800',
};

export default function Incidents({ incidents, canManage }: { incidents: IncidentRow[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        occurred_at: new Date().toISOString().slice(0, 16),
        location: '',
        description: '',
        persons_involved: '',
        injury_details: '',
        severity: 'minor',
        actions_taken: '',
        follow_up_required: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/incidents', { onSuccess: () => { setAdding(false); reset(); } });
    }

    function setStatus(id: number, status: string) {
        router.put(`/incidents/${id}`, { status }, { preserveScroll: true });
    }

    return (
        <AppShell title="Incidents">
            <Head title="Incidents" />

            <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                + Report incident
            </button>

            <div className="space-y-2">
                {incidents.map((i) => (
                    <Card key={i.id} className={i.severity === 'critical' ? 'border-l-4 border-l-status-red' : ''}>
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-bold text-brand-dark">{i.title}</div>
                                <div className="text-xs text-slate-400">
                                    {new Date(i.occurred_at).toLocaleString('en-GB')}
                                    {i.location && ` · ${i.location}`}
                                    {' · reported by '}{i.reported_by}
                                </div>
                                <p className="text-sm text-slate-500 mt-1">{i.description}</p>
                                {i.persons_involved && <p className="text-xs text-slate-500 mt-1">Involved: {i.persons_involved}</p>}
                                {i.injury_details && <p className="text-xs text-red-600 mt-1">Injury: {i.injury_details}</p>}
                                {i.actions_taken && <p className="text-xs text-slate-500 mt-1">Actions: {i.actions_taken}</p>}
                                {i.follow_up_required && <p className="text-xs text-amber-700 font-semibold mt-1">⚠ Follow-up required</p>}
                            </div>
                            <div className="shrink-0 flex flex-col items-end gap-1">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${SEVERITY_STYLE[i.severity]}`}>{i.severity}</span>
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[i.status]}`}>{i.status}</span>
                            </div>
                        </div>
                        {canManage && i.status !== 'closed' && (
                            <div className="flex gap-1.5 mt-2">
                                {i.status === 'open' && (
                                    <button onClick={() => setStatus(i.id, 'under-review')} className="rounded-full bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1.5">
                                        Mark under review
                                    </button>
                                )}
                                <button onClick={() => setStatus(i.id, 'closed')} className="rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5">
                                    ✓ Close
                                </button>
                            </div>
                        )}
                    </Card>
                ))}
                {incidents.length === 0 && <Card><p className="text-slate-500">No incidents logged.</p></Card>}
            </div>

            <Modal open={adding} title="Report an incident" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Date & time
                            <input type="datetime-local" value={data.occurred_at} onChange={(e) => setData('occurred_at', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Location
                            <input value={data.location} onChange={(e) => setData('location', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Description
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} required />
                    </label>
                    <label className="block text-sm font-medium">
                        Persons involved
                        <input value={data.persons_involved} onChange={(e) => setData('persons_involved', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Injury details (if any)
                        <textarea value={data.injury_details} onChange={(e) => setData('injury_details', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Severity
                        <select value={data.severity} onChange={(e) => setData('severity', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            <option value="minor">Minor</option>
                            <option value="moderate">Moderate</option>
                            <option value="serious">Serious</option>
                            <option value="critical">Critical</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium">
                        Actions taken
                        <textarea value={data.actions_taken} onChange={(e) => setData('actions_taken', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" checked={data.follow_up_required} onChange={(e) => setData('follow_up_required', e.target.checked)} className="rounded border-slate-300" />
                        Follow-up required
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Submit incident report
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
