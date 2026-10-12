import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

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
    manager_review: string | null;
    learning: string | null;
    prevention_action: string | null;
    follow_up_required: boolean;
    status: 'open' | 'under-review' | 'closed';
    reviewed_at: string | null;
    closed_at: string | null;
    reported_by: string;
    about: { id: number; type: string; name: string; url: string | null } | null;
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

interface Props {
    incidents: IncidentRow[];
    canManage: boolean;
    relatedOptions: Record<string, { id: number; name: string }[]>;
    context: { id: number; type: string; name: string; url: string | null } | null;
}

export default function Incidents({ incidents, canManage, relatedOptions, context }: Props) {
    const [adding, setAdding] = useState(false);
    const [reviewing, setReviewing] = useState<IncidentRow | null>(null);
    const [review, setReview] = useState({ status: 'under-review', actions_taken: '', manager_review: '', learning: '', prevention_action: '', follow_up_required: false });
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
        related_type: context?.type ?? '',
        related_id: context ? String(context.id) : '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/incidents', { onSuccess: () => { setAdding(false); reset(); } });
    }

    function openReview(incident: IncidentRow) {
        setReviewing(incident);
        setReview({
            status: incident.status === 'open' ? 'under-review' : incident.status,
            actions_taken: incident.actions_taken ?? '',
            manager_review: incident.manager_review ?? '',
            learning: incident.learning ?? '',
            prevention_action: incident.prevention_action ?? '',
            follow_up_required: incident.follow_up_required,
        });
    }

    function saveReview(event: FormEvent) {
        event.preventDefault();
        if (!reviewing) return;
        router.put(`/incidents/${reviewing.id}`, review, { preserveScroll: true, onSuccess: () => setReviewing(null) });
    }

    return (
        <AppShell title="Incidents">
            <Head title="Incidents" />
            <ModuleHero eyebrow="Safety management" title="Incidents" description="Record events properly, coordinate follow-up and learn from patterns." icon="🚨" tone="rose" />

            {context && <div className="mb-4 flex items-center justify-between rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm"><span>Showing incidents linked to <b>{context.name}</b></span><Link href="/incidents" className="font-bold text-brand">Show all</Link></div>}

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
                                {i.actions_taken && <p className="text-xs text-slate-500 mt-1">Immediate action: {i.actions_taken}</p>}
                                {i.manager_review && <p className="text-xs text-slate-500 mt-1">Manager review: {i.manager_review}</p>}
                                {i.learning && <p className="text-xs text-slate-500 mt-1">Learning: {i.learning}</p>}
                                {i.prevention_action && <p className="text-xs text-slate-500 mt-1">Prevention: {i.prevention_action}</p>}
                                {i.follow_up_required && <p className="text-xs text-amber-700 font-semibold mt-1">⚠ Follow-up required</p>}
                                {i.about && <p className="mt-1 text-xs">Linked to: {i.about.url ? <Link href={i.about.url} className="font-bold text-brand">{i.about.name}</Link> : i.about.name}</p>}
                            </div>
                            <div className="shrink-0 flex flex-col items-end gap-1">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${SEVERITY_STYLE[i.severity]}`}>{i.severity}</span>
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[i.status]}`}>{i.status}</span>
                            </div>
                        </div>
                        <div className="incident-cycle-4a" aria-label="Incident progress">
                            <span className="is-done">1 <b>Reported</b></span>
                            <i />
                            <span className={i.status !== 'open' ? 'is-done' : ''}>2 <b>Reviewed</b></span>
                            <i />
                            <span className={i.actions_taken || i.prevention_action ? 'is-done' : ''}>3 <b>Actioned</b></span>
                            <i />
                            <span className={i.status === 'closed' ? 'is-done' : ''}>4 <b>Closed & learned</b></span>
                        </div>
                        {canManage && <button onClick={() => openReview(i)} className="incident-review-button-4a">{i.status === 'closed' ? 'View or reopen review' : 'Continue review'} →</button>}
                    </Card>
                ))}
                {incidents.length === 0 && <Card><p className="text-slate-500">No incidents logged.</p></Card>}
            </div>

            <Modal open={adding} title="Report an incident" onClose={() => setAdding(false)} size="wide">
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
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">Link to record
                            <select value={data.related_type} onChange={(e) => setData((current) => ({ ...current, related_type: e.target.value, related_id: '' }))} className="mt-1 w-full rounded-lg border border-slate-300 px-3">
                                <option value="">No linked record</option>
                                {Object.keys(relatedOptions).map((type) => <option key={type} value={type}>{type[0].toUpperCase() + type.slice(1)}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">Record
                            <select value={data.related_id} onChange={(e) => setData('related_id', e.target.value)} disabled={!data.related_type} className="mt-1 w-full rounded-lg border border-slate-300 px-3 disabled:bg-slate-100">
                                <option value="">{data.related_type ? 'Choose record' : 'Not linked'}</option>
                                {(relatedOptions[data.related_type] ?? []).map((record) => <option key={record.id} value={record.id}>{record.name}</option>)}
                            </select>
                        </label>
                    </div>
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

            <Modal open={Boolean(reviewing)} title="Incident review and learning" onClose={() => setReviewing(null)}>
                <form onSubmit={saveReview} className="space-y-3">
                    <div className="incident-review-intro-4a"><b>{reviewing?.title}</b><p>Record what happened next so the report leads to a visible action and learning.</p></div>
                    <label className="block text-sm font-medium">Review stage
                        <select value={review.status} onChange={(event) => setReview({ ...review, status: event.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            <option value="open">Reported</option><option value="under-review">Under review</option><option value="closed">Closed and learning recorded</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium">Immediate actions taken
                        <textarea value={review.actions_taken} onChange={(event) => setReview({ ...review, actions_taken: event.target.value })} rows={2} className="mt-1 w-full rounded-lg border border-slate-300 p-3" placeholder="What was done at the time to keep people safe?" />
                    </label>
                    <label className="block text-sm font-medium">Manager review
                        <textarea value={review.manager_review} onChange={(event) => setReview({ ...review, manager_review: event.target.value })} rows={3} className="mt-1 w-full rounded-lg border border-slate-300 p-3" placeholder="What did the review establish?" />
                    </label>
                    <label className="block text-sm font-medium">What did we learn?
                        <textarea value={review.learning} onChange={(event) => setReview({ ...review, learning: event.target.value })} rows={3} className="mt-1 w-full rounded-lg border border-slate-300 p-3" placeholder="Record the useful learning in plain language." />
                    </label>
                    <label className="block text-sm font-medium">What will prevent a repeat?
                        <textarea value={review.prevention_action} onChange={(event) => setReview({ ...review, prevention_action: event.target.value })} rows={3} className="mt-1 w-full rounded-lg border border-slate-300 p-3" placeholder="Updated guidance, risk control, task or other action." />
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium"><input type="checkbox" checked={review.follow_up_required} onChange={(event) => setReview({ ...review, follow_up_required: event.target.checked })} className="rounded border-slate-300" />Further follow-up is still required</label>
                    <button type="submit" className="w-full rounded-lg bg-brand text-white font-bold py-3">Save review</button>
                </form>
            </Modal>
        </AppShell>
    );
}
