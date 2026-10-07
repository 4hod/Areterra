import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import StatusPill from '../components/StatusPill';
import ModuleHero from '../components/ModuleHero';
import { confirmDialog } from '../utils/dialogs';

interface Assessment {
    id: number;
    title: string;
    category: string;
    version: number;
    is_current: boolean;
    description: string | null;
    likelihood: number;
    severity: number;
    score: number;
    level: 'green' | 'amber' | 'red';
    control_measures: string | null;
    review_date: string | null;
    status: string;
    signed_off_by: string | null;
    signed_off_at: string | null;
}

function MatrixPicker({ label, value, onChange }: { label: string; value: number; onChange: (v: number) => void }) {
    return (
        <div>
            <div className="text-sm font-medium mb-1">{label}</div>
            <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((v) => (
                    <button
                        key={v}
                        type="button"
                        onClick={() => onChange(v)}
                        className={`h-11 w-11 rounded-lg font-bold ${
                            value === v ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'
                        }`}
                    >
                        {v}
                    </button>
                ))}
            </div>
        </div>
    );
}

interface Props {
    assessments: Assessment[];
    canManage: boolean;
    filters: { q: string; category: string; history: boolean };
    categories: string[];
}

export default function RiskAssessments({ assessments, canManage, filters, categories }: Props) {
    const [editing, setEditing] = useState<Assessment | 'new' | null>(null);
    const { data, setData, post, put, processing, reset } = useForm({
        title: '',
        category: 'general',
        description: '',
        likelihood: 2,
        severity: 2,
        control_measures: '',
        review_date: '',
    });

    function open(a: Assessment | 'new') {
        setEditing(a);
        if (a === 'new') {
            reset();
        } else {
            setData({
                title: a.title,
                category: a.category,
                description: a.description ?? '',
                likelihood: a.likelihood,
                severity: a.severity,
                control_measures: a.control_measures ?? '',
                review_date: a.review_date ?? '',
            });
        }
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        const opts = { onSuccess: () => setEditing(null) };
        if (editing === 'new') post('/risk-assessments', opts);
        else if (editing) put(`/risk-assessments/${editing.id}`, opts);
    }

    return (
        <AppShell title="Risk Assessments">
            <Head title="Risk Assessments" />
            <ModuleHero eyebrow="Safety planning" title="Risk assessments" description="Balance independence and safety with clear, living assessments." icon="⚖️" tone="amber" />

            {canManage && (
                <button onClick={() => open('new')} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + New risk assessment
                </button>
            )}

            <Card className="mb-4">
                <div className="grid gap-2 sm:grid-cols-[1fr_220px_auto]">
                    <input defaultValue={filters.q} placeholder="Search activity, hazard or control…" onKeyDown={(e) => {
                        if (e.key === 'Enter') router.get('/risk-assessments', { ...filters, q: e.currentTarget.value }, { preserveState: true });
                    }} className="rounded-xl border border-slate-300 px-4" />
                    <select value={filters.category} onChange={(e) => router.get('/risk-assessments', { ...filters, category: e.target.value }, { preserveState: true })} className="rounded-xl border border-slate-300 px-3 bg-white">
                        <option value="">All categories</option>
                        {categories.map((category) => <option key={category}>{category}</option>)}
                    </select>
                    <button onClick={() => router.get('/risk-assessments', { ...filters, history: !filters.history }, { preserveState: true })} className="rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold">
                        {filters.history ? 'Current only' : 'Version history'}
                    </button>
                </div>
            </Card>

            {assessments.length === 0 && (
                <Card><p className="text-slate-500">No risk assessments yet.</p></Card>
            )}

            <div className="space-y-2">
                {assessments.map((a) => (
                    <Card key={a.id} className={`border-l-4 ${a.level === 'red' ? 'border-l-status-red' : a.level === 'amber' ? 'border-l-status-amber' : 'border-l-status-green'}`}>
                        <div className="flex items-center justify-between gap-2">
                            <div className="min-w-0">
                                <div className="font-bold text-brand-dark">{a.title}</div>
                                <div className="text-xs text-slate-400">
                                    <span className="mr-2 rounded-full bg-blue-50 px-2 py-0.5 font-bold text-blue-700">{a.category}</span>
                                    <span className="mr-2">v{a.version}{a.is_current ? ' · current' : ' · superseded'}</span>
                                    Likelihood {a.likelihood} × Severity {a.severity} = <b>risk {a.score}</b>
                                    {a.signed_off_by
                                        ? ` · signed off by ${a.signed_off_by}`
                                        : ' · not signed off'}
                                    {a.review_date && ` · review ${new Date(a.review_date).toLocaleDateString('en-GB')}`}
                                </div>
                                {a.control_measures && <p className="text-sm text-slate-500 mt-1">{a.control_measures}</p>}
                            </div>
                            {canManage && (
                                <div className="flex flex-col gap-1 shrink-0">
                                    <button onClick={() => open(a)} className="rounded-full bg-slate-100 text-xs font-bold px-3 py-1.5">
                                        Edit
                                    </button>
                                    {!a.signed_off_by && (
                                        <button
                                            onClick={() => router.post(`/risk-assessments/${a.id}/sign-off`)}
                                            className="rounded-full bg-brand text-white text-xs font-bold px-3 py-1.5"
                                        >
                                            Sign off
                                        </button>
                                    )}
                                    {a.is_current && (
                                        <button onClick={() => router.post(`/risk-assessments/${a.id}/new-version`)} className="rounded-full bg-blue-50 text-blue-700 text-xs font-bold px-3 py-1.5">New version</button>
                                    )}
                                    {a.status === 'draft' && !a.signed_off_by && (
                                        <button
                                            onClick={async () => (await confirmDialog(`Delete the draft risk assessment “${a.title}”?`)) && router.delete(`/risk-assessments/${a.id}`)}
                                            className="rounded-full bg-red-50 text-red-700 text-xs font-bold px-3 py-1.5"
                                        >
                                            Delete draft
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </Card>
                ))}
            </div>

            <Modal
                open={editing !== null}
                title={editing === 'new' ? 'New risk assessment' : 'Edit risk assessment'}
                onClose={() => setEditing(null)}
            >
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Activity / hazard
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <label className="block text-sm font-medium">
                        Category
                        <input list="risk-categories" value={data.category} onChange={(e) => setData('category', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        <datalist id="risk-categories">
                            {['animal care','cooking','outings','woodwork','transport','site','general', ...categories].filter((v,i,a) => a.indexOf(v) === i).map((category) => <option key={category}>{category}</option>)}
                        </datalist>
                    </label>
                    <label className="block text-sm font-medium">
                        Description
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <MatrixPicker label="Likelihood" value={data.likelihood} onChange={(v) => setData('likelihood', v)} />
                        <MatrixPicker label="Severity" value={data.severity} onChange={(v) => setData('severity', v)} />
                    </div>
                    <p className="text-sm">
                        Risk score: <b>{data.likelihood * data.severity}</b>{' '}
                        <span className={data.likelihood * data.severity >= 15 ? 'text-status-red font-bold' : data.likelihood * data.severity >= 8 ? 'text-status-amber font-bold' : 'text-status-green font-bold'}>
                            ({data.likelihood * data.severity >= 15 ? 'high' : data.likelihood * data.severity >= 8 ? 'medium' : 'low'})
                        </span>
                    </p>
                    <label className="block text-sm font-medium">
                        Control measures
                        <textarea value={data.control_measures} onChange={(e) => setData('control_measures', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} />
                    </label>
                    <label className="block text-sm font-medium">
                        Review date
                        <input type="date" value={data.review_date} onChange={(e) => setData('review_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Save
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
