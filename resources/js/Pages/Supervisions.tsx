import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface History {
    id: number;
    type: string;
    date: string;
    duration_minutes: number | null;
    supervisor: string;
    discussion: string | null;
    actions_agreed: string | null;
    development_notes: string | null;
    next_due_date: string | null;
    staff_signed_off: boolean;
}

interface StaffRow {
    subject_key: string;
    name: string;
    job_title: string | null;
    latest_date: string | null;
    overdue: boolean;
    history: History[];
}

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const typeLabel = (t: string) => t.replace(/_/g, ' ');

export default function Supervisions({ staff, types }: { staff: StaffRow[]; types: string[] }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        subject_key: staff[0]?.subject_key ?? '',
        type: 'supervision',
        date: new Date().toISOString().slice(0, 10),
        duration_minutes: '' as string | number,
        discussion: '',
        actions_agreed: '',
        development_notes: '',
        next_due_date: '',
        staff_signed_off: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/supervisions', {
            onSuccess: () => {
                setAdding(false);
                reset();
            },
        });
    }

    return (
        <AppShell title="Supervisions & Appraisals">
            <Head title="Supervisions" />
            <ModuleHero eyebrow="Staff development" title="Supervisions" description="Plan meaningful conversations, actions and professional growth." icon="🗣️" tone="blue" />

            <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                + Record supervision
            </button>

            <div className="space-y-3">
                {staff.map((s) => (
                    <Card key={s.subject_key}>
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="font-bold text-brand-dark">{s.name}</div>
                                <div className="text-xs text-slate-400 capitalize">{s.job_title}</div>
                            </div>
                            <div className="text-right">
                                {s.latest_date ? (
                                    <div className="text-sm font-semibold">{fmt(s.latest_date)}</div>
                                ) : (
                                    <div className="text-sm text-slate-400">Never</div>
                                )}
                                {s.overdue && <div className="text-xs font-bold text-red-600">⚠ Overdue (12+ weeks)</div>}
                            </div>
                        </div>
                        {s.history.length > 0 && (
                            <details className="mt-2">
                                <summary className="text-xs font-bold text-brand cursor-pointer">
                                    History ({s.history.length})
                                </summary>
                                <ul className="mt-2 divide-y divide-slate-100 text-sm">
                                    {s.history.map((h) => (
                                        <li key={h.id} className="py-2">
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold capitalize">
                                                    {typeLabel(h.type)} · {fmt(h.date)}
                                                </span>
                                                <span className="text-xs text-slate-400">
                                                    {h.supervisor}
                                                    {h.staff_signed_off && ' · ✓ signed off'}
                                                </span>
                                            </div>
                                            {h.discussion && <p className="text-slate-500 mt-1">{h.discussion}</p>}
                                            {h.actions_agreed && (
                                                <p className="text-slate-500 mt-1"><b>Actions:</b> {h.actions_agreed}</p>
                                            )}
                                            {h.next_due_date && (
                                                <p className="text-xs text-slate-400 mt-1">Next due {fmt(h.next_due_date)}</p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </Card>
                ))}
            </div>

            <Modal open={adding} title="Record supervision" onClose={() => setAdding(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Staff member
                            <select value={data.subject_key} onChange={(e) => setData('subject_key', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                {staff.map((s) => (
                                    <option key={s.subject_key} value={s.subject_key}>{s.name}</option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Type
                            <select value={data.type} onChange={(e) => setData('type', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white capitalize">
                                {types.map((t) => (
                                    <option key={t} value={t}>{typeLabel(t)}</option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={data.date} onChange={(e) => setData('date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Duration (mins)
                            <input type="number" min={0} value={data.duration_minutes} onChange={(e) => setData('duration_minutes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Discussion notes
                        <textarea value={data.discussion} onChange={(e) => setData('discussion', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} />
                    </label>
                    <label className="block text-sm font-medium">
                        Actions agreed
                        <textarea value={data.actions_agreed} onChange={(e) => setData('actions_agreed', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Development notes
                        <textarea value={data.development_notes} onChange={(e) => setData('development_notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <div className="grid grid-cols-2 gap-3 items-end">
                        <label className="block text-sm font-medium">
                            Next due
                            <input type="date" value={data.next_due_date} onChange={(e) => setData('next_due_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="flex items-center gap-2 text-sm font-medium pb-3">
                            <input type="checkbox" checked={data.staff_signed_off} onChange={(e) => setData('staff_signed_off', e.target.checked)} className="rounded border-slate-300" />
                            Staff signed off
                        </label>
                    </div>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Save
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
