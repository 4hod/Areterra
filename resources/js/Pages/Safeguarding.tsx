import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import { promptDialog } from '../utils/dialogs';

interface Concern {
    id: number;
    member: string | null;
    reporter: string;
    source: string;
    date: string;
    details: string;
    actions_taken: string | null;
    status: string;
    closed_at: string | null;
}

interface Props {
    concerns: Concern[];
    members: { id: number; name: string }[];
}

export default function Safeguarding({ concerns, members }: Props) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        member_id: '' as string | number,
        date: new Date().toISOString().slice(0, 10),
        details: '',
        actions_taken: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/safeguarding', { onSuccess: () => { setAdding(false); reset(); } });
    }

    const open = concerns.filter((c) => c.status === 'open');

    return (
        <AppShell title="Safeguarding">
            <Head title="Safeguarding" />

            <p className="text-xs text-red-700 font-semibold mb-3">
                🛡️ Restricted section — access is logged. {open.length} open concern{open.length === 1 ? '' : 's'}.
            </p>

            <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                + Log concern
            </button>

            <div className="space-y-2">
                {concerns.map((c) => (
                    <Card key={c.id} className={c.status === 'open' ? 'border-l-4 border-l-status-red' : 'opacity-75'}>
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <div className="font-bold text-brand-dark">
                                    {c.member ?? 'General'}
                                    <span className="ml-2 text-xs font-semibold text-slate-400">
                                        {new Date(c.date).toLocaleDateString('en-GB')} · {c.reporter}
                                        {c.source === 'end_of_day' && ' · from end-of-day record'}
                                    </span>
                                </div>
                                <p className="text-sm mt-1 whitespace-pre-wrap">{c.details}</p>
                                {c.actions_taken && (
                                    <p className="text-sm mt-1 text-slate-500"><b>Actions:</b> {c.actions_taken}</p>
                                )}
                            </div>
                            <div className="shrink-0 flex flex-col items-end gap-1">
                                <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${c.status === 'open' ? 'bg-red-100 text-red-800' : 'bg-slate-200 text-slate-500'}`}>
                                    {c.status}
                                </span>
                                {c.status === 'open' && (
                                    <button
                                        onClick={async () => {
                                            const actions = await promptDialog('Actions taken before closing:', c.actions_taken ?? '');
                                            if (actions !== null) {
                                                router.put(`/safeguarding/${c.id}`, { status: 'closed', actions_taken: actions });
                                            }
                                        }}
                                        className="text-xs font-bold text-brand"
                                    >
                                        Close
                                    </button>
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
                {concerns.length === 0 && <Card><p className="text-slate-500">No safeguarding concerns logged.</p></Card>}
            </div>

            <Modal open={adding} title="Log safeguarding concern" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Member (optional)
                            <select value={data.member_id} onChange={(e) => setData('member_id', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                <option value="">General / not member-specific</option>
                                {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Details of concern
                        <textarea value={data.details} onChange={(e) => setData('details', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={4} required />
                    </label>
                    <label className="block text-sm font-medium">
                        Actions taken so far
                        <textarea value={data.actions_taken} onChange={(e) => setData('actions_taken', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Log concern
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
