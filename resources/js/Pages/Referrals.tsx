import { Head, router } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Row {
    id: number;
    referrer_name: string;
    referrer_email: string | null;
    referrer_phone: string | null;
    organisation: string | null;
    person_name: string;
    details: string | null;
    status: string;
    reviewer: string | null;
    created_at: string;
    pending_days: number | null;
}

const STATUS_STYLE: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    accepted: 'bg-emerald-100 text-emerald-800',
    declined: 'bg-slate-200 text-slate-500',
};

export default function Referrals({ referrals }: { referrals: Row[] }) {
    return (
        <AppShell title="Referrals">
            <Head title="Referrals" />

            <p className="text-xs text-slate-400 mb-3">
                Submissions from the public referral form at <b>/refer</b>. Accepting creates a member profile.
            </p>

            {referrals.length === 0 && <Card><p className="text-slate-500">No referrals received yet.</p></Card>}

            <div className="space-y-2">
                {referrals.map((r) => (
                    <Card key={r.id} className={r.pending_days !== null && r.pending_days > 5 ? 'border-l-4 border-l-status-amber' : ''}>
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <div className="font-bold text-brand-dark">{r.person_name}</div>
                                <div className="text-xs text-slate-400">
                                    Referred by {r.referrer_name}
                                    {r.organisation && ` (${r.organisation})`} · {new Date(r.created_at).toLocaleDateString('en-GB')}
                                    {r.pending_days !== null && r.pending_days > 5 && (
                                        <span className="text-amber-600 font-bold"> · pending {r.pending_days} days</span>
                                    )}
                                </div>
                                {r.details && <p className="text-sm mt-1 text-slate-600">{r.details}</p>}
                                <div className="text-xs text-slate-400 mt-1">
                                    {r.referrer_email && <a href={`mailto:${r.referrer_email}`} className="text-brand font-semibold mr-3">✉️ {r.referrer_email}</a>}
                                    {r.referrer_phone && <a href={`tel:${r.referrer_phone}`} className="text-brand font-semibold">📞 {r.referrer_phone}</a>}
                                </div>
                            </div>
                            <div className="shrink-0 flex flex-col items-end gap-1.5">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[r.status]}`}>
                                    {r.status}
                                </span>
                                {r.status === 'pending' && (
                                    <div className="flex gap-1">
                                        <button
                                            onClick={() => router.put(`/referrals/${r.id}/review`, { status: 'accepted' })}
                                            className="rounded-full bg-emerald-100 text-emerald-800 text-xs font-bold px-3 py-1.5"
                                        >
                                            ✓ Accept
                                        </button>
                                        <button
                                            onClick={() => router.put(`/referrals/${r.id}/review`, { status: 'declined' })}
                                            className="rounded-full bg-slate-100 text-slate-500 text-xs font-bold px-3 py-1.5"
                                        >
                                            Decline
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </AppShell>
    );
}
