import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface MonitoringData {
    id?: number;
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

interface AnimalRow {
    id: number;
    name: string;
    species: string;
    monitoring: MonitoringData | null;
}

interface Props {
    date: string;
    bySpecies: Record<string, AnimalRow[]>;
    done: number;
    total: number;
}

const empty = (): MonitoringData => ({
    weight_grams: null, body_condition: null, coat_condition: '', appetite: '',
    droppings: '', behaviour: '', enrichment: '', enrichment_minutes: null, notes: '', concern: false,
});

export default function Monitoring({ date, bySpecies, done, total }: Props) {
    const [editing, setEditing] = useState<AnimalRow | null>(null);
    const [m, setM] = useState<MonitoringData>(empty());

    function open(animal: AnimalRow) {
        setEditing(animal);
        setM(animal.monitoring ?? empty());
    }

    function save() {
        if (!editing) return;
        router.post(
            `/animals/${editing.id}/monitoring`,
            { ...m, monitor_date: date },
            { onSuccess: () => setEditing(null) },
        );
    }

    const pct = total > 0 ? Math.round((done / total) * 100) : 0;

    return (
        <AppShell title="Daily Monitoring">
            <Head title="Daily Monitoring" />
            <ModuleHero eyebrow="Daily assurance" title="Daily monitoring" description="Capture wellbeing, care and environmental checks before small issues grow." icon="📊" tone="teal" />

            <div className="flex items-center gap-3 mb-3">
                <input
                    type="date"
                    value={date}
                    onChange={(e) => router.get('/monitoring', { date: e.target.value })}
                    className="rounded-lg border border-slate-300 px-3 bg-white"
                />
                <span className="text-sm font-semibold text-slate-500">
                    {done} of {total} animals monitored
                </span>
            </div>

            {/* Progress bar */}
            <div className="h-3 rounded-full bg-slate-200 overflow-hidden mb-4">
                <div className={`h-full ${pct === 100 ? 'bg-status-green' : 'bg-brand'} transition-all`} style={{ width: `${pct}%` }} />
            </div>

            <div className="space-y-4">
                {Object.entries(bySpecies).map(([species, animals]) => {
                    const speciesDone = animals.filter((a) => a.monitoring).length;
                    return (
                        <Card
                            key={species}
                            title={`${species}s`}
                            action={
                                <span className={`text-xs font-bold ${speciesDone === animals.length ? 'text-status-green' : 'text-slate-400'}`}>
                                    {speciesDone === animals.length ? '✓ All done' : `${speciesDone}/${animals.length}`}
                                </span>
                            }
                        >
                            <div className="flex flex-wrap gap-2">
                                {animals.map((a) => (
                                    <button
                                        key={a.id}
                                        onClick={() => open(a)}
                                        className={`rounded-full px-4 py-2 text-sm font-semibold ${
                                            a.monitoring
                                                ? a.monitoring.concern
                                                    ? 'bg-amber-100 text-amber-800'
                                                    : 'bg-emerald-100 text-emerald-800'
                                                : 'bg-slate-100 text-slate-600'
                                        }`}
                                    >
                                        {a.monitoring ? (a.monitoring.concern ? '⚠ ' : '✓ ') : ''}{a.name}
                                    </button>
                                ))}
                            </div>
                        </Card>
                    );
                })}
            </div>

            <Modal open={editing !== null} title={`Monitoring — ${editing?.name ?? ''}`} onClose={() => setEditing(null)}>
                <div className="grid grid-cols-2 gap-3">
                    <label className="text-sm font-medium">
                        Weight (g)
                        <input type="number" value={m.weight_grams ?? ''} onChange={(e) => setM({ ...m, weight_grams: e.target.value ? Number(e.target.value) : null })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="text-sm font-medium">
                        Body condition (1–5)
                        <input type="number" min={1} max={5} value={m.body_condition ?? ''} onChange={(e) => setM({ ...m, body_condition: e.target.value ? Number(e.target.value) : null })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    {(
                        [
                            ['coat_condition', 'Coat/feather condition'], ['appetite', 'Appetite'],
                            ['droppings', 'Droppings'], ['behaviour', 'Behaviour'],
                        ] as const
                    ).map(([key, label]) => (
                        <label key={key} className="text-sm font-medium">
                            {label}
                            <input value={(m[key] as string) ?? ''} onChange={(e) => setM({ ...m, [key]: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    ))}
                    <label className="text-sm font-medium">
                        Enrichment given
                        <input value={m.enrichment ?? ''} onChange={(e) => setM({ ...m, enrichment: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="text-sm font-medium">
                        Enrichment (mins)
                        <input type="number" value={m.enrichment_minutes ?? ''} onChange={(e) => setM({ ...m, enrichment_minutes: e.target.value ? Number(e.target.value) : null })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                </div>
                <textarea value={m.notes ?? ''} onChange={(e) => setM({ ...m, notes: e.target.value })} placeholder="Notes" className="mt-3 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                <label className="mt-3 flex items-center gap-2 text-sm font-medium text-red-700">
                    <input type="checkbox" checked={m.concern} onChange={(e) => setM({ ...m, concern: e.target.checked })} className="rounded border-slate-300" />
                    ⚠️ Flag a concern (notifies managers)
                </label>
                <div className="flex gap-2 mt-4">
                    <button onClick={save} className="flex-1 rounded-lg bg-brand text-white font-bold py-3">
                        Save
                    </button>
                    {editing && (
                        <Link href={`/animals/${editing.id}`} className="rounded-lg bg-slate-100 text-slate-600 font-bold py-3 px-4">
                            Profile →
                        </Link>
                    )}
                </div>
            </Modal>
        </AppShell>
    );
}
