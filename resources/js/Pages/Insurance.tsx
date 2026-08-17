import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import EmptyState from '../components/EmptyState';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Policy {
    id: number;
    policy_type: string;
    provider: string | null;
    policy_number: string | null;
    renewal_date: string | null;
    annual_cost: number | null;
    notes: string | null;
}

export default function Insurance({ policies, canManage }: { policies: Policy[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        policy_type: '',
        provider: '',
        policy_number: '',
        renewal_date: '',
        annual_cost: '',
        notes: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/insurance', { onSuccess: () => { setAdding(false); reset(); } });
    }

    function daysUntil(date: string) {
        return Math.ceil((new Date(date).getTime() - Date.now()) / 86400000);
    }

    return (
        <AppShell title="Insurance">
            <Head title="Insurance" />
            <ModuleHero eyebrow="Cover & protection" title="Insurance" description="Keep every policy's renewal date in one place, with a heads-up before anything lapses." icon="🛡️" tone="slate" />

            {canManage && (
                <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + Add policy
                </button>
            )}

            <div className="space-y-2">
                {policies.map((p) => {
                    const days = p.renewal_date ? daysUntil(p.renewal_date) : null;
                    const overdue = days !== null && days < 0;
                    const dueSoon = days !== null && days >= 0 && days <= 30;
                    return (
                        <Card key={p.id} className={overdue ? 'border-l-4 border-l-status-red' : dueSoon ? 'border-l-4 border-l-status-amber' : ''}>
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <div className="font-bold text-brand-dark">
                                        {p.policy_type}
                                        {p.provider && <span className="font-normal text-slate-500"> · {p.provider}</span>}
                                    </div>
                                    <div className="text-xs text-slate-400">
                                        {p.policy_number && `Policy #${p.policy_number} · `}
                                        {p.annual_cost !== null && `£${p.annual_cost.toLocaleString()}/yr · `}
                                        {p.renewal_date && `renews ${new Date(p.renewal_date).toLocaleDateString('en-GB')}`}
                                    </div>
                                    {p.notes && <p className="text-sm text-slate-500 mt-1">{p.notes}</p>}
                                </div>
                                {days !== null && (
                                    <span className={`shrink-0 text-xs font-bold px-2.5 py-0.5 rounded-full ${overdue ? 'bg-red-100 text-red-700' : dueSoon ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'}`}>
                                        {overdue ? `Expired ${Math.abs(days)}d ago` : `${days}d left`}
                                    </span>
                                )}
                            </div>
                        </Card>
                    );
                })}
                {policies.length === 0 && <Card><EmptyState icon="🛡️" text="No insurance policies logged yet." /></Card>}
            </div>

            <Modal open={adding} title="Add insurance policy" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Policy type
                        <input value={data.policy_type} onChange={(e) => setData('policy_type', e.target.value)} placeholder="Public Liability, Employer's Liability, Animal…" className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Provider
                            <input value={data.provider} onChange={(e) => setData('provider', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Policy number
                            <input value={data.policy_number} onChange={(e) => setData('policy_number', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Renewal date
                            <input type="date" value={data.renewal_date} onChange={(e) => setData('renewal_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Annual cost £
                            <input type="number" min="0" step="0.01" value={data.annual_cost} onChange={(e) => setData('annual_cost', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Notes
                        <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add policy
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
