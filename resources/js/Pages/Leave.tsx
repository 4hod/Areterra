import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import StatusPill from '../components/StatusPill';

interface LeaveRow {
    id: number;
    type: string;
    start_date: string;
    end_date: string;
    days: string | number;
    reason: string | null;
    status: string;
    review_notes: string | null;
    user?: { name: string };
}

interface Props {
    balance: { entitlement: number; taken: number; remaining: number };
    myRequests: LeaveRow[];
    types: string[];
    isManager: boolean;
    pending: LeaveRow[];
    upcoming: LeaveRow[];
}

const STATUS_STYLE: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    declined: 'bg-red-100 text-red-800',
};

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

export default function Leave({ balance, myRequests, types, isManager, pending, upcoming }: Props) {
    const [requesting, setRequesting] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'annual',
        start_date: '',
        end_date: '',
        reason: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/leave', {
            onSuccess: () => {
                setRequesting(false);
                reset();
            },
        });
    }

    function review(row: LeaveRow, status: 'approved' | 'declined') {
        router.put(`/leave/${row.id}/review`, { status });
    }

    return (
        <AppShell title="Leave">
            <Head title="Leave" />

            <div className="grid grid-cols-3 gap-3 mb-4">
                <Card>
                    <div className="text-2xl font-extrabold text-brand-dark">{balance.entitlement}</div>
                    <div className="text-xs text-slate-500 font-medium">Days entitlement</div>
                </Card>
                <Card>
                    <div className="text-2xl font-extrabold text-status-amber">{balance.taken}</div>
                    <div className="text-xs text-slate-500 font-medium">Taken</div>
                </Card>
                <Card>
                    <div className="text-2xl font-extrabold text-status-green">{balance.remaining}</div>
                    <div className="text-xs text-slate-500 font-medium">Remaining</div>
                </Card>
            </div>

            <button
                onClick={() => setRequesting(true)}
                className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4"
            >
                + Request leave
            </button>

            {isManager && pending.length > 0 && (
                <Card title={`Awaiting approval (${pending.length})`} className="mb-4 border-l-4 border-l-status-amber">
                    <ul className="divide-y divide-slate-100">
                        {pending.map((r) => (
                            <li key={r.id} className="py-3">
                                <div className="flex items-center justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="font-bold text-sm">{r.user?.name}</div>
                                        <div className="text-sm text-slate-500 capitalize">
                                            {r.type} · {fmt(r.start_date)} – {fmt(r.end_date)} ({r.days} days)
                                        </div>
                                        {r.reason && <div className="text-sm text-slate-400">{r.reason}</div>}
                                    </div>
                                    <div className="flex gap-1 shrink-0">
                                        <button
                                            onClick={() => review(r, 'approved')}
                                            className="rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs px-3 py-2"
                                        >
                                            ✓ Approve
                                        </button>
                                        <button
                                            onClick={() => review(r, 'declined')}
                                            className="rounded-full bg-red-100 text-red-700 font-bold text-xs px-3 py-2"
                                        >
                                            ✕ Decline
                                        </button>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            {isManager && upcoming.length > 0 && (
                <Card title="Upcoming approved leave" className="mb-4">
                    <ul className="divide-y divide-slate-100 text-sm">
                        {upcoming.map((r) => (
                            <li key={r.id} className="py-2 flex items-center justify-between">
                                <span className="font-semibold">{r.user?.name}</span>
                                <span className="text-slate-500 capitalize">
                                    {r.type} · {fmt(r.start_date)} – {fmt(r.end_date)}
                                </span>
                            </li>
                        ))}
                    </ul>

                    {/* Calendar view: current month, approved leave marked */}
                    <div className="mt-4">
                        <div className="text-xs font-bold text-slate-400 uppercase mb-1.5">
                            {new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}
                        </div>
                        <div className="grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-slate-400">
                            {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => (
                                <div key={i}>{d}</div>
                            ))}
                        </div>
                        <div className="grid grid-cols-7 gap-1 mt-1">
                            {(() => {
                                const now = new Date();
                                const first = new Date(now.getFullYear(), now.getMonth(), 1);
                                const days = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
                                const offset = (first.getDay() + 6) % 7;
                                const cells = [];
                                for (let i = 0; i < offset; i++) cells.push(<div key={`pad-${i}`} />);
                                for (let d = 1; d <= days; d++) {
                                    const dateStr = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                                    const onLeave = upcoming.filter((r) => r.start_date.slice(0, 10) <= dateStr && r.end_date.slice(0, 10) >= dateStr);
                                    cells.push(
                                        <div
                                            key={d}
                                            title={onLeave.map((r) => r.user?.name).join(', ')}
                                            className={`rounded-md py-1.5 text-xs font-semibold ${
                                                onLeave.length > 0
                                                    ? 'bg-brand text-white'
                                                    : d === now.getDate()
                                                      ? 'bg-slate-200 text-slate-700'
                                                      : 'bg-slate-50 text-slate-400'
                                            }`}
                                        >
                                            {d}
                                            {onLeave.length > 0 && <div className="text-[8px] leading-none">{onLeave.length}</div>}
                                        </div>,
                                    );
                                }
                                return cells;
                            })()}
                        </div>
                    </div>
                </Card>
            )}

            <Card title="My requests">
                {myRequests.length === 0 && <p className="text-sm text-slate-400">No leave requests yet.</p>}
                <ul className="divide-y divide-slate-100">
                    {myRequests.map((r) => (
                        <li key={r.id} className="py-3 flex items-center justify-between gap-2">
                            <div>
                                <div className="font-semibold text-sm capitalize">
                                    {r.type} · {fmt(r.start_date)} – {fmt(r.end_date)}
                                </div>
                                <div className="text-xs text-slate-400">
                                    {r.days} days
                                    {r.review_notes && ` · ${r.review_notes}`}
                                </div>
                            </div>
                            <span
                                className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[r.status]}`}
                            >
                                {r.status}
                            </span>
                        </li>
                    ))}
                </ul>
            </Card>

            <Modal open={requesting} title="Request leave" onClose={() => setRequesting(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Type
                        <select
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 capitalize bg-white"
                        >
                            {types.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            First day
                            <input
                                type="date"
                                value={data.start_date}
                                onChange={(e) => setData('start_date', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                required
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Last day
                            <input
                                type="date"
                                value={data.end_date}
                                onChange={(e) => setData('end_date', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                required
                            />
                        </label>
                    </div>
                    {errors.end_date && <p className="text-red-600 text-sm">{errors.end_date}</p>}
                    <label className="block text-sm font-medium">
                        Reason (optional)
                        <textarea
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Submit request
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
