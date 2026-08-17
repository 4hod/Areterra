import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { WelfareStatus } from '../../types';
import { recordRecentlyViewed } from '../../utils/recentlyViewed';
import ModuleHero from '../../components/ModuleHero';
import Sparkline from '../../components/Sparkline';

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
    next_due_date: string | null;
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
        joined_date: string | null;
        care_requirements: string | null;
        feeding_notes: string | null;
    };
    welfareChecks: {
        id: number;
        status: WelfareStatus;
        notes: string | null;
        concern: boolean;
        fed: boolean;
        treats_given: boolean;
        treats_notes: string | null;
        created_at: string;
        user: { name: string };
    }[];
    monitoring: Monitoring[];
    todayMonitoring: Monitoring | null;
    canEdit: boolean;
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

export default function Show({ animal, welfareChecks, monitoring, todayMonitoring, vetRecords, canEdit }: Props) {
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [details, setDetails] = useState({
        dob: animal.dob ?? '',
        microchip: animal.microchip ?? '',
        sex: animal.sex ?? '',
        breed: animal.breed ?? '',
        joined_date: animal.joined_date ?? '',
        care_requirements: animal.care_requirements ?? '',
        feeding_notes: animal.feeding_notes ?? '',
    });

    function saveDetails() {
        router.put(`/animals/${animal.id}`, details, { onSuccess: () => setDetailsOpen(false) });
    }

    useEffect(() => {
        recordRecentlyViewed({ title: animal.name, url: `/animals/${animal.id}`, type: 'Animal' });
    }, [animal.id]);

    const [checkOpen, setCheckOpen] = useState(false);
    const [checkStatus, setCheckStatus] = useState<WelfareStatus>('green');
    const [checkNotes, setCheckNotes] = useState('');
    const [monitorOpen, setMonitorOpen] = useState(false);
    const [m, setM] = useState<Monitoring>(todayMonitoring ?? emptyMonitoring());
    const [vetOpen, setVetOpen] = useState(false);
    const [vet, setVet] = useState({ visit_date: new Date().toISOString().slice(0, 10), next_due_date: '', reason: '', treatment: '', vet_name: '', notes: '' });

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
            <ModuleHero eyebrow="Animal profile" title="Animal record" description="Care notes, routines, health information and history in one place." icon="🐾" tone="green" />

            <div className="flex items-center gap-4 mb-4">
                <div>
                    <div className="text-xl font-extrabold text-brand-dark">{animal.name}</div>
                    <div className="text-slate-500 text-sm">
                        {animal.species}
                        {animal.breed && ` · ${animal.breed}`}
                        {animal.sex && ` · ${animal.sex}`}
                    </div>
                </div>
                <div className="ml-auto flex items-center gap-2">
                    <StatusPill status={animal.welfare_status} label={`Welfare: ${animal.welfare_status}`} />
                    {canEdit && (
                        <button onClick={() => setDetailsOpen(true)} className="rounded-full bg-ink/[0.06] text-ink/70 text-xs font-bold px-3 py-2">
                            ✏️ Edit details
                        </button>
                    )}
                </div>
            </div>

            {(animal.joined_date || animal.care_requirements || animal.feeding_notes) && (
                <div className="grid md:grid-cols-3 gap-3 mb-4">
                    {animal.joined_date && (
                        <Card>
                            <div className="text-xs font-bold uppercase tracking-wide text-ink/40">Joined Areterra</div>
                            <div className="font-semibold text-brand-dark mt-1">
                                {new Date(animal.joined_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                            </div>
                        </Card>
                    )}
                    {animal.care_requirements && (
                        <Card title="🛁 Care Requirements">
                            <p className="text-sm text-ink/70 whitespace-pre-wrap">{animal.care_requirements}</p>
                        </Card>
                    )}
                    {animal.feeding_notes && (
                        <Card title="🥣 Feeding">
                            <p className="text-sm text-ink/70 whitespace-pre-wrap">{animal.feeding_notes}</p>
                        </Card>
                    )}
                </div>
            )}

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
                                <div className="flex gap-2 mt-1 text-xs">
                                    <span className={c.fed ? 'text-emerald-700' : 'text-red-600 font-bold'}>
                                        {c.fed ? '🍽️ Fed' : '🚫 Not fed'}
                                    </span>
                                    {c.treats_given && (
                                        <span className="text-amber-700">🍪 Treats{c.treats_notes && `: ${c.treats_notes}`}</span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>

                <Card title="Daily monitoring history">
                    {monitoring.length === 0 && <p className="text-sm text-slate-400">No monitoring recorded yet.</p>}
                    {monitoring.filter((m) => m.weight_grams !== null).length >= 2 && (
                        <div className="mb-3">
                            <div className="text-xs font-bold uppercase tracking-wide text-ink/40 mb-1">Weight trend (g)</div>
                            <Sparkline
                                values={[...monitoring]
                                    .filter((m) => m.weight_grams !== null)
                                    .reverse()
                                    .map((m) => m.weight_grams as number)}
                            />
                        </div>
                    )}
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
                        {vetRecords.map((v) => {
                            const daysUntilDue = v.next_due_date
                                ? Math.ceil((new Date(v.next_due_date).getTime() - Date.now()) / 86400000)
                                : null;
                            return (
                                <li key={v.id} className="py-2">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold">
                                            {new Date(v.visit_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                                            {v.vet_name && <span className="text-slate-400"> · {v.vet_name}</span>}
                                        </span>
                                        <span className="text-slate-500">{v.reason}</span>
                                    </div>
                                    {v.treatment && <div className="text-slate-500 mt-1">{v.treatment}</div>}
                                    {v.next_due_date && daysUntilDue !== null && (
                                        <div className={`text-xs font-bold mt-1 ${daysUntilDue < 0 ? 'text-status-red' : daysUntilDue <= 14 ? 'text-status-amber' : 'text-ink/40'}`}>
                                            {daysUntilDue < 0
                                                ? `⚠ Overdue since ${new Date(v.next_due_date).toLocaleDateString('en-GB')}`
                                                : `Next due ${new Date(v.next_due_date).toLocaleDateString('en-GB')} (${daysUntilDue}d)`}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </Card>
            </div>

            {/* Vet record modal */}
            <Modal open={vetOpen} title={`Vet visit — ${animal.name}`} onClose={() => setVetOpen(false)}>
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Visit date
                            <input type="date" value={vet.visit_date} onChange={(e) => setVet({ ...vet, visit_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Next due (optional)
                            <input type="date" value={vet.next_due_date} onChange={(e) => setVet({ ...vet, next_due_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
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

            <Modal open={detailsOpen} title={`Edit — ${animal.name}`} onClose={() => setDetailsOpen(false)}>
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Date of birth
                            <input
                                type="date"
                                value={details.dob}
                                onChange={(e) => setDetails({ ...details, dob: e.target.value })}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Joined Areterra
                            <input
                                type="date"
                                value={details.joined_date}
                                onChange={(e) => setDetails({ ...details, joined_date: e.target.value })}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Microchip
                            <input
                                value={details.microchip}
                                onChange={(e) => setDetails({ ...details, microchip: e.target.value })}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Sex
                            <select
                                value={details.sex}
                                onChange={(e) => setDetails({ ...details, sex: e.target.value })}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white"
                            >
                                <option value="">Unknown</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Breed
                        <input
                            value={details.breed}
                            onChange={(e) => setDetails({ ...details, breed: e.target.value })}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        🛁 Care requirements
                        <textarea
                            value={details.care_requirements}
                            onChange={(e) => setDetails({ ...details, care_requirements: e.target.value })}
                            placeholder="e.g. Free range during day. Secure in coop at dusk. Check for mites weekly."
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={3}
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        🥣 Feeding
                        <textarea
                            value={details.feeding_notes}
                            onChange={(e) => setDetails({ ...details, feeding_notes: e.target.value })}
                            placeholder="e.g. Layers pellets, mixed corn and fresh water daily."
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={3}
                        />
                    </label>
                    <button onClick={saveDetails} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        Save details
                    </button>
                </div>
            </Modal>
        </AppShell>
    );
}
