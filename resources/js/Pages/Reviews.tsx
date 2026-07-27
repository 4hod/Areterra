import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface MemberRow {
    id: number;
    name: string;
    last_review: string | null;
    next_review: string | null;
    overdue: boolean;
}

interface Review {
    id: number;
    member: string;
    member_id: number;
    review_date: string;
    outcomes: string | null;
    actions: string | null;
    next_review_date: string | null;
    conducted_by: string;
}

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

export default function Reviews({ members, reviews, canManage }: { members: MemberRow[]; reviews: Review[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        member_id: members[0]?.id ?? 0,
        review_date: new Date().toISOString().slice(0, 10),
        outcomes: '',
        actions: '',
        next_review_date: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/reviews', {
            onSuccess: () => {
                setAdding(false);
                reset();
            },
        });
    }

    return (
        <AppShell title="Member Reviews">
            <Head title="Member Reviews" />
            <ModuleHero eyebrow="Person-centred planning" title="Member reviews" description="Prepare, complete and follow up reviews with a clear view of progress." icon="🔄" tone="teal" />

            {canManage && (
                <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + Record review
                </button>
            )}

            <Card title="Review status" className="mb-4">
                <ul className="divide-y divide-slate-100 text-sm">
                    {members.map((m) => (
                        <li key={m.id} className="py-2 flex items-center justify-between">
                            <span className="font-semibold">{m.name}</span>
                            <span className={m.overdue ? 'text-red-600 font-bold text-xs' : 'text-slate-500 text-xs'}>
                                {m.next_review
                                    ? `${m.overdue ? '⚠ Was due' : 'Next'} ${fmt(m.next_review)}`
                                    : '⚠ No review scheduled'}
                            </span>
                        </li>
                    ))}
                </ul>
            </Card>

            <Card title="Recent reviews">
                {reviews.length === 0 && <p className="text-sm text-slate-400">No reviews recorded yet.</p>}
                <ul className="divide-y divide-slate-100">
                    {reviews.map((r) => (
                        <li key={r.id} className="py-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-bold">{r.member}</span>
                                <span className="text-slate-400 text-xs">
                                    {fmt(r.review_date)} · {r.conducted_by}
                                </span>
                            </div>
                            {r.outcomes && <p className="text-sm text-slate-500 mt-1">{r.outcomes}</p>}
                            {r.actions && <p className="text-sm text-slate-500 mt-1"><b>Actions:</b> {r.actions}</p>}
                            {r.next_review_date && (
                                <p className="text-xs text-slate-400 mt-1">Next review {fmt(r.next_review_date)}</p>
                            )}
                        </li>
                    ))}
                </ul>
            </Card>

            <Modal open={adding} title="Record member review" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Member
                            <select value={data.member_id} onChange={(e) => setData('member_id', Number(e.target.value))} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                {members.map((m) => (
                                    <option key={m.id} value={m.id}>{m.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Review date
                            <input type="date" value={data.review_date} onChange={(e) => setData('review_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Outcomes
                        <textarea value={data.outcomes} onChange={(e) => setData('outcomes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} />
                    </label>
                    <label className="block text-sm font-medium">
                        Actions
                        <textarea value={data.actions} onChange={(e) => setData('actions', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Next review date
                        <input type="date" value={data.next_review_date} onChange={(e) => setData('next_review_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Save review
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
