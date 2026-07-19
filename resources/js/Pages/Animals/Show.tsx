import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { WelfareStatus } from '../../types';

interface Monitoring {
    id?: number;
    monitor_date: string;
    weight_grams: number | null;
    body_condition: number | null;
    coat_condition: string | null;
    appetite: string | null;
    droppings: string | null;
    behaviour: string | null;
    enrichment: string | null;
    enrichment_minutes: number | null;
    notes: string | null;
    concern: boolean;
}

interface VetRecordRow {
    id: number;
    visit_date: string;
    reason: string | null;
    treatment: string | null;
    vet_name: string | null;
    notes: string | null;
}

interface Props {
    vetRecords: VetRecordRow[];
    animal: {
        id: number;
        name: string;
        species: string;
        dob: string | null;
        microchip: string | null;
        sex: string | null;
        breed: string | null;
        status: string;
        welfare_status: WelfareStatus;
    };
    welfareChecks: {
        id: number;
        status: WelfareStatus;
        notes: string | null;
        concern: boolean;
        created_at: string;
        user: { name: string };
    }[];
    monitoring: Monitoring[];
    todayMonitoring: Monitoring | null;
}

const emptyMonitoring = (): Monitoring => ({
    monitor_date: new Date().toISOString().slice(0, 10),
    weight_grams: null,
    body_condition: null,
    coat_condition: '',
    appetite: '',
    droppings: '',
    behaviour: '',
    enrichment: '',
    enrichment_minutes: null,
    notes: '',
    concern: false,
});

export default function Show({ animal, welfareChecks, monitoring, todayMonitoring, vetRecords }: Props) {
    const [checkOpen, setCheckOpen] = useState(false);
    const [checkStatus, setCheckStatus] = useState<WelfareStatus>('green');
    const [checkNotes, setCheckNotes] = useState('');
    const [monitorOpen, setMonitorOpen] = useState(false);
    const [m, setM] = useState<Monitoring>(todayMonitoring ?? emptyMonitoring());
    const [vetOpen, setVetOpen] = useState(false);
    const [vet, setVet] = useState({ visit_date: new Date().toISOString().slice(0, 10), reason: '', treatment: '', vet_name: '', notes: '' });

    function saveCheck() {
        router.post(
            `/animals/${animal.id}/welfare-checks`,
            { status: checkStatus, notes: checkNotes, concern: checkStatus !== 'green' },
            { onSuccess: () => setCheckOpen(false) },
        );
    }

    function saveMonitoring() {
        router.post(`/animals/${animal.id}/monitoring`, { ...m }, { onSuccess: () => setMonitorOpen(false) });
    }

    return (
        <AppShell title={animal.name}>
            <Head title={animal.name} />

            <div className="flex items-center gap-4 mb-4">
                <div>
                    <div className="text-xl font-extrabold text-brand-dark">{animal.name}</div>
                    <div className="text-slate-500 text-sm">
                        {animal.species}
                        {animal.breed && ` · ${animal.breed}`}
                        {animal.sex && ` · ${animal.sex}`}
                    </div>
                </div>
                <div className="ml-auto">
                    <StatusPill status={animal.welfare_status} label={`Welfare: ${animal.welfare_status}`} />
                </div>
            </div>

            <div className="flex gap-2 mb-4">
                <button onClick={() => setCheckOpen(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5">
                    ✓ Welfare check
                </button>
                <button
                    onClick={() => {
                        setM(todayMonitoring ?? emptyMonitoring());
                        setMonitorOpen(true);
                    }}
                    className="rounded-full bg-brand-dark text-white font-semibold text-sm px-4 py-2.5"
                >
                    📊 Daily monitoring
                </button>
                <button onClick={() => setVetOpen(true)} className="rounded-full bg-slate-700 text-white font-semibold text-sm px-4 py-2.5">
                    🩺 Vet visit
                </button>
            </div>

            <div className="grid md:grid-cols-2 gap-3">
                <Card title="Recent welfare checks">
                    {welfareChecks.length === 0 && <p className="text-sm text-slate-400">No checks logged yet.</p>}
                    <ul className="divide-y divide-slate-100 text-sm">
                        {welfareChecks.map((c) => (
                            <li key={c.id} className="py-2">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        {new Date(c.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                                        <span className="text-slate-400"> · {c.user.name}</span>
                                    </span>
                                    <StatusPill status={c.status} />
                                </div>
                                {c.notes && <div className="text-slate-500 mt-1">{c.notes}</div>}
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title="Daily monitoring history">
                    {monitoring.length === 0 && <p className="text-sm text-slate-400">No monitoring recorded yet.</p>}
                    <ul className="divide-y divide-slate-100 text-sm">
                        {monitoring.map((row) => (
                            <li key={row.id} className="py-2">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        {new Date(row.monitor_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                                        {row.concern && <span className="ml-1">⚠️</span>}
                                    </span>
                                    <span className="text-slate-500">
                                        {row.weight_grams != null && `${row.weight_grams}g`}
                                        {row.body_condition != null && ` · BCS ${row.body_condition}/5`}
                                    </span>
                                </div>
                                {row.notes && <div className="text-slate-500 mt-1">{row.notes}</div>}
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card title="Vet records" className="md:col-span-2">
                    {vetRecords.length === 0 && <p className="text-sm text-slate-400">No vet visits recorded.</p>}
                    <ul className="divide-y divide-slate-100 text-sm">
                        {vetRecords.map((v) => (
                            <li key={v.id} className="py-2">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold">
                                        {new Date(v.visit_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        {v.vet_name && <span className="text-slate-400"> · {v.vet_name}</span>}
                                    </span>
                                    <span className="text-slate-500">{v.reason}</span>
                                </div>
                                {v.treatment && <div className="text-slate-500 mt-1">{v.treatment}</div>}
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            {/* Vet record modal */}
            <Modal open={vetOpen} title={`Vet visit — ${animal.name}`} onClose={() => setVetOpen(false)}>
                <div className="space-y-3">
                    <label className="block text-sm font-medium">
                        Visit date
                        <input type="date" value={vet.visit_date} onChange={(e) => setVet({ ...vet, visit_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Reason
                            <input value={vet.reason} onChange={(e) => setVet({ ...vet, reason: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Vet name
                            <input value={vet.vet_name} onChange={(e) => setVet({ ...vet, vet_name: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Treatment
                        <textarea value={vet.treatment} onChange={(e) => setVet({ ...vet, treatment: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button
                        onClick={() => router.post(`/animals/${animal.id}/vet-records`, { ...vet }, { onSuccess: () => setVetOpen(false) })}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3"
                    >
                        Save vet record
                    </button>
                </div>
            </Modal>

            {/* Welfare check modal */}
            <Modal open={checkOpen} title={`Welfare check — ${animal.name}`} onClose={() => setCheckOpen(false)}>
                <div className="space-y-4">
                    <div className="flex gap-2">
                        {(['green', 'amber', 'red'] as const).map((s) => (
                            <button
                                key={s}
                                onClick={() => setCheckStatus(s)}
                                className={`flex-1 rounded-lg py-3 font-bold capitalize ${
                                    checkStatus === s
                                        ? s === 'green'
                                            ? 'bg-status-green text-white'
                                            : s === 'amber'
                                              ? 'bg-status-amber text-white'
                                              : 'bg-status-red text-white'
                                        : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                    <textarea
                        value={checkNotes}
                        onChange={(e) => setCheckNotes(e.target.value)}
                        placeholder="Notes"
                        className="w-full rounded-lg border border-slate-300 p-3"
                        rows={3}
                    />
                    <button onClick={saveCheck} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        Save check
                    </button>
                </div>
            </Modal>

            {/* Daily monitoring modal */}
            <Modal open={monitorOpen} title={`Daily monitoring — ${animal.name}`} onClose={() => setMonitorOpen(false)}>
                <div className="grid grid-cols-2 gap-3">
                    <label className="text-sm font-medium">
                        Weight (g)
                        <input
                            type="number"
                            value={m.weight_grams ?? ''}
                            onChange={(e) => setM({ ...m, weight_grams: e.target.value ? Number(e.target.value) : null })}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Body condition (1–5)
                        <input
                            type="number"
                            min={1}
                            max={5}
                            value={m.body_condition ?? ''}
                            onChange={(e) => setM({ ...m, body_condition: e.target.value ? Number(e.target.value) : null })}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    {(
                        [
                            ['coat_condition', 'Coat condition'],
                            ['appetite', 'Appetite'],
                            ['droppings', 'Droppings'],
                            ['behaviour', 'Behaviour'],
                        ] as const
                    ).map(([key, label]) => (
                        <label key={key} className="text-sm font-medium">
                            {label}
                            <input
                                value={(m[key] as string) ?? ''}
                                onChange={(e) => setM({ ...m, [key]: e.target.value })}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                    ))}
                    <label className="text-sm font-medium">
                        Enrichment given
                        <input
                            value={m.enrichment ?? ''}
                            onChange={(e) => setM({ ...m, enrichment: e.target.value })}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Enrichment (mins)
                        <input
                            type="number"
                            value={m.enrichment_minutes ?? ''}
                            onChange={(e) => setM({ ...m, enrichment_minutes: e.target.value ? Number(e.target.value) : null })}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                </div>
                <textarea
                    value={m.notes ?? ''}
                    onChange={(e) => setM({ ...m, notes: e.target.value })}
                    placeholder="Notes"
                    className="mt-3 w-full rounded-lg border border-slate-300 p-3"
                    rows={2}
                />
                <label className="mt-3 flex items-center gap-2 text-sm font-medium text-red-700">
                    <input
                        type="checkbox"
                        checked={m.concern}
                        onChange={(e) => setM({ ...m, concern: e.target.checked })}
                        className="rounded border-slate-300"
                    />
                    ⚠️ Flag a concern
                </label>
                <button onClick={saveMonitoring} className="mt-4 w-full rounded-lg bg-brand text-white font-bold py-3">
                    Save monitoring
                </button>
            </Modal>
        </AppShell>
    );
}
