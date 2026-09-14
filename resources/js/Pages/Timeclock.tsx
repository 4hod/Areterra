import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Entry { id: number; user: string; work_date: string; minutes: number; notes: string | null; }
interface Contract { id: number; name: string; contracted_hours: number | null; }
interface Props { contractedHours: number | null; additionalEntries: Entry[]; allAdditionalEntries: Entry[]; staffContracts: Contract[]; isManager: boolean; }
const hm = (mins: number) => `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2, '0')}m`;
const dateLabel = (date: string) => new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

export default function Timeclock({ contractedHours, additionalEntries, allAdditionalEntries, staffContracts, isManager }: Props) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ work_date: new Date().toISOString().slice(0, 10), hours: 1, notes: '' });
    function submit(e: FormEvent) { e.preventDefault(); post('/timeclock/additional', { onSuccess: () => { setAdding(false); reset(); } }); }

    return <AppShell title="Working Hours">
        <Head title="Working Hours" />
        <ModuleHero eyebrow="Working time" title="Working hours" description="Your normal contracted hours are automatic. Only log time worked in addition to them." icon="⏱️" tone="blue" />
        <div className="grid md:grid-cols-2 gap-4 mb-4">
            <Card><div className="text-sm text-slate-500">Normal contracted hours</div><div className="text-4xl font-extrabold text-brand-dark my-1">{contractedHours === null ? '—' : `${contractedHours}h`}</div><div className="text-xs text-slate-400">per week · no clocking in or out needed</div></Card>
            <Card><div className="text-sm text-slate-500">Additional hours logged</div><div className="text-4xl font-extrabold text-brand-dark my-1">{hm(additionalEntries.reduce((n, e) => n + e.minutes, 0))}</div><button onClick={() => setAdding(true)} className="mt-2 rounded-full bg-brand text-white font-bold px-5 py-2.5">+ Log additional hours</button></Card>
        </div>
        <Card title="My additional hours" className="mb-4">{additionalEntries.length === 0 ? <p className="text-sm text-slate-400">No additional hours logged.</p> : <ul className="divide-y divide-slate-100">{additionalEntries.map((e) => <li key={e.id} className="py-3 flex gap-3 justify-between text-sm"><div><b className="text-brand-dark">{dateLabel(e.work_date)}</b><div className="text-slate-500">{e.notes}</div></div><strong>{hm(e.minutes)}</strong></li>)}</ul>}</Card>
        {isManager && <><Card title="Contracted hours" className="mb-4"><div className="space-y-2">{staffContracts.map((s) => <div key={s.id} className="flex items-center gap-3"><span className="flex-1 font-semibold text-sm">{s.name}</span><input type="number" min="0" step="0.5" defaultValue={s.contracted_hours ?? ''} placeholder="Hours/week" className="w-32 rounded-lg border border-slate-300 px-3" onBlur={(e) => router.put(`/timeclock/contracts/${s.id}`, { contracted_hours: e.target.value === '' ? null : Number(e.target.value) })} /></div>)}</div></Card><Card title="All additional hours">{allAdditionalEntries.length === 0 ? <p className="text-sm text-slate-400">No additional hours this week.</p> : <ul className="divide-y divide-slate-100">{allAdditionalEntries.map((e) => <li key={e.id} className="py-2 grid grid-cols-[1fr_auto_auto] gap-3 text-sm"><b>{e.user}</b><span>{dateLabel(e.work_date)}</span><strong>{hm(e.minutes)}</strong><span className="col-span-3 text-xs text-slate-500">{e.notes}</span></li>)}</ul>}</Card></>}
        <Modal open={adding} title="Log additional hours" onClose={() => setAdding(false)}><form onSubmit={submit} className="space-y-3"><label className="block text-sm font-medium">Date<input type="date" max={new Date().toISOString().slice(0, 10)} value={data.work_date} onChange={(e) => setData('work_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required /></label><label className="block text-sm font-medium">Additional hours<input type="number" min="0.25" max="24" step="0.25" value={data.hours} onChange={(e) => setData('hours', Number(e.target.value))} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required /></label><label className="block text-sm font-medium">Reason / work completed<textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} required /></label><button disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3">Save additional hours</button></form></Modal>
    </AppShell>;
}
