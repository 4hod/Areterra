import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';

interface Period {
    id: number;
    label: string;
    start_date: string;
    end_date: string;
    pay_date: string | null;
    status: string;
    entries_count: number;
    total: number;
}

interface RosterMember {
    id: number;
    key: string;
    name: string;
    ni_number: string | null;
    job_title: string | null;
    email: string | null;
    phone: string | null;
    active: boolean;
    has_account: boolean;
    current_rate: number | null;
    contracted_hours: number | null;
}

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const STATUS_STYLE: Record<string, string> = {
    draft: 'bg-slate-200 text-slate-600',
    finalised: 'bg-amber-100 text-amber-800',
    paid: 'bg-emerald-100 text-emerald-800',
};

export default function Index({ periods, roster }: { periods: Period[]; roster: RosterMember[] }) {
    const [creating, setCreating] = useState(false);
    const [addingStaff, setAddingStaff] = useState(false);

    const periodForm = useForm({ label: '', start_date: '', end_date: '', pay_date: '', authorised_by: '' });
    const staffForm = useForm({ name: '', ni_number: '', job_title: '', email: '', phone: '', hourly_rate: '' });

    function createPeriod(e: FormEvent) {
        e.preventDefault();
        periodForm.post('/payroll/periods');
    }

    function addStaff(e: FormEvent) {
        e.preventDefault();
        staffForm.post('/payroll/roster', {
            onSuccess: () => {
                setAddingStaff(false);
                staffForm.reset();
            },
        });
    }

    return (
        <AppShell title="Payroll">
            <Head title="Payroll" />

            <div className="flex gap-2 mb-4">
                <button onClick={() => setCreating(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5">
                    + New pay period
                </button>
                <button onClick={() => setAddingStaff(true)} className="rounded-full bg-brand-dark text-white font-semibold text-sm px-5 py-2.5">
                    + Add roster staff
                </button>
            </div>

            <Card title="Pay periods" className="mb-4">
                {periods.length === 0 && <p className="text-sm text-slate-400">No pay periods yet.</p>}
                <ul className="divide-y divide-slate-100">
                    {periods.map((p) => (
                        <li key={p.id}>
                            <Link href={`/payroll/periods/${p.id}`} className="py-3 flex items-center justify-between gap-2 hover:bg-slate-50 -mx-2 px-2 rounded">
                                <div>
                                    <div className="font-bold text-sm text-brand-dark">{p.label}</div>
                                    <div className="text-xs text-slate-400">
                                        {fmt(p.start_date)} – {fmt(p.end_date)}
                                        {p.pay_date && ` · Pay date ${fmt(p.pay_date)}`}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="font-extrabold text-brand-dark">£{p.total.toFixed(2)}</span>
                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[p.status]}`}>
                                        {p.status}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Staff & pay rates">
                <p className="text-xs text-slate-400 mb-2">
                    All staff — roster and Hub accounts. Rate changes keep history; overtime auto-fills at 1.5×.
                </p>
                <ul className="divide-y divide-slate-100">
                    {roster.map((s) => (
                        <li key={s.key} className="py-2 flex items-center justify-between text-sm gap-2">
                            <div className="min-w-0">
                                <span className="font-semibold">{s.name}</span>
                                {!s.has_account && <span className="ml-2 text-[10px] font-bold text-amber-600 uppercase">No Hub account yet</span>}
                                {!s.active && <span className="ml-2 text-xs text-slate-400">(inactive)</span>}
                                <div className="text-xs text-slate-400">
                                    {[s.job_title, s.contracted_hours && `${s.contracted_hours}h/wk contracted`].filter(Boolean).join(' · ')}
                                </div>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
                                <span className="font-bold text-brand-dark">
                                    {s.current_rate !== null ? `£${s.current_rate.toFixed(2)}/hr` : '—'}
                                </span>
                                <button
                                    onClick={() => {
                                        const rate = prompt(`New hourly rate for ${s.name} (£):`, s.current_rate?.toFixed(2) ?? '');
                                        if (!rate) return;
                                        const contracted = prompt('Contracted hours per week (optional):', s.contracted_hours?.toString() ?? '');
                                        router.post('/payroll/rates', {
                                            key: s.key,
                                            hourly_rate: Number(rate),
                                            contracted_hours: contracted ? Number(contracted) : null,
                                        });
                                    }}
                                    className="text-xs font-bold text-brand"
                                >
                                    Set rate
                                </button>
                                {!s.has_account && (
                                    <>
                                        <button
                                            onClick={() => {
                                                const name = prompt('Name:', s.name);
                                                if (!name) return;
                                                const job = prompt('Job title:', s.job_title ?? '') ?? '';
                                                router.put(`/payroll/roster/${s.id}`, { name, job_title: job });
                                            }}
                                            className="text-xs font-bold text-slate-400"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() => confirm(`Remove ${s.name} from the roster?`) && router.delete(`/payroll/roster/${s.id}`)}
                                            className="text-xs font-bold text-red-400"
                                        >
                                            Remove
                                        </button>
                                    </>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </Card>

            <Modal open={creating} title="New pay period" onClose={() => setCreating(false)}>
                <form onSubmit={createPeriod} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Label
                        <input
                            value={periodForm.data.label}
                            onChange={(e) => periodForm.setData('label', e.target.value)}
                            placeholder="e.g. July 2026"
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            required
                        />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Period start
                            <input type="date" value={periodForm.data.start_date} onChange={(e) => periodForm.setData('start_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Period end
                            <input type="date" value={periodForm.data.end_date} onChange={(e) => periodForm.setData('end_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Pay date (can differ from period end, e.g. 28th)
                        <input type="date" value={periodForm.data.pay_date} onChange={(e) => periodForm.setData('pay_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Authorised by
                        <input value={periodForm.data.authorised_by} onChange={(e) => periodForm.setData('authorised_by', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <p className="text-xs text-slate-400">A row is pre-filled for each active roster member at their current rate.</p>
                    <button type="submit" disabled={periodForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Create period
                    </button>
                </form>
            </Modal>

            <Modal open={addingStaff} title="Add roster staff" onClose={() => setAddingStaff(false)}>
                <form onSubmit={addStaff} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Name
                        <input value={staffForm.data.name} onChange={(e) => staffForm.setData('name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            N.I. number
                            <input value={staffForm.data.ni_number} onChange={(e) => staffForm.setData('ni_number', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Hourly rate (£)
                            <input type="number" step="0.01" min="0" value={staffForm.data.hourly_rate} onChange={(e) => staffForm.setData('hourly_rate', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Job title
                        <input value={staffForm.data.job_title} onChange={(e) => staffForm.setData('job_title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Email
                            <input type="email" value={staffForm.data.email} onChange={(e) => staffForm.setData('email', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Phone
                            <input value={staffForm.data.phone} onChange={(e) => staffForm.setData('phone', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <button type="submit" disabled={staffForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add to roster
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
