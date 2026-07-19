import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { WelfareStatus } from '../../types';

const SPECIES_EMOJI: Record<string, string> = {
    Macaw: '🦜',
    Chinchilla: '🐭',
    Degu: '🐹',
    'Guinea Pig': '🐹',
    Rabbit: '🐰',
    Chicken: '🐔',
};

interface AnimalRow {
    id: number;
    name: string;
    species: string;
    welfare_status: WelfareStatus;
    checked_today: boolean;
}

interface Flagged {
    animal_id: number;
    status: 'amber' | 'red';
    notes: string;
}

export default function Index({ bySpecies }: { species: string[]; bySpecies: Record<string, AnimalRow[]> }) {
    const [checking, setChecking] = useState<string | null>(null);
    const [flagged, setFlagged] = useState<Flagged[]>([]);

    function toggleFlag(id: number) {
        setFlagged((f) =>
            f.some((x) => x.animal_id === id)
                ? f.filter((x) => x.animal_id !== id)
                : [...f, { animal_id: id, status: 'amber', notes: '' }],
        );
    }

    function updateFlag(id: number, patch: Partial<Flagged>) {
        setFlagged((f) => f.map((x) => (x.animal_id === id ? { ...x, ...patch } : x)));
    }

    function submitGroup() {
        if (!checking) return;
        router.post(
            '/welfare-checks/species',
            { species: checking, flagged: flagged.map((f) => ({ ...f })) },
            {
                onSuccess: () => {
                    setChecking(null);
                    setFlagged([]);
                },
            },
        );
    }

    return (
        <AppShell title="Animals">
            <Head title="Animals" />

            <div className="space-y-4">
                {Object.entries(bySpecies).map(([species, animals]) => {
                    const allChecked = animals.every((a) => a.checked_today);
                    return (
                        <Card
                            key={species}
                            title={`${SPECIES_EMOJI[species] ?? '🐾'} ${species}s (${animals.length})`}
                            action={
                                <button
                                    onClick={() => {
                                        setChecking(species);
                                        setFlagged([]);
                                    }}
                                    className={`rounded-full text-xs font-bold px-3 py-1.5 ${
                                        allChecked ? 'bg-emerald-100 text-emerald-800' : 'bg-brand text-white'
                                    }`}
                                >
                                    {allChecked ? '✓ Checked today' : 'Welfare check'}
                                </button>
                            }
                        >
                            <div className="flex flex-wrap gap-2">
                                {animals.map((a) => (
                                    <Link
                                        key={a.id}
                                        href={`/animals/${a.id}`}
                                        className="flex items-center gap-2 rounded-full border border-slate-200 pl-3 pr-2 py-1.5 hover:bg-slate-50"
                                    >
                                        <span className="font-semibold text-sm text-brand-dark">{a.name}</span>
                                        <span
                                            className={`h-2.5 w-2.5 rounded-full ${
                                                a.welfare_status === 'green'
                                                    ? 'bg-status-green'
                                                    : a.welfare_status === 'amber'
                                                      ? 'bg-status-amber'
                                                      : 'bg-status-red'
                                            }`}
                                            title={`Welfare: ${a.welfare_status}`}
                                        />
                                    </Link>
                                ))}
                            </div>
                        </Card>
                    );
                })}
            </div>

            <Modal open={checking !== null} title={`${checking} welfare check`} onClose={() => setChecking(null)}>
                <p className="text-sm text-slate-500 mb-4">
                    Everyone is recorded as healthy unless you flag them below.
                </p>
                <div className="space-y-3">
                    {(bySpecies[checking ?? ''] ?? []).map((a) => {
                        const flag = flagged.find((f) => f.animal_id === a.id);
                        return (
                            <div key={a.id} className={`rounded-lg border p-3 ${flag ? 'border-amber-300 bg-amber-50' : 'border-slate-200'}`}>
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold">{a.name}</span>
                                    <button
                                        onClick={() => toggleFlag(a.id)}
                                        className={`text-xs font-bold rounded-full px-3 py-1.5 ${
                                            flag ? 'bg-amber-200 text-amber-900' : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {flag ? 'Flagged' : 'Flag concern'}
                                    </button>
                                </div>
                                {flag && (
                                    <div className="mt-2 space-y-2">
                                        <div className="flex gap-2">
                                            {(['amber', 'red'] as const).map((s) => (
                                                <button
                                                    key={s}
                                                    onClick={() => updateFlag(a.id, { status: s })}
                                                    className={`flex-1 rounded-lg py-2 text-sm font-bold capitalize ${
                                                        flag.status === s
                                                            ? s === 'amber'
                                                                ? 'bg-status-amber text-white'
                                                                : 'bg-status-red text-white'
                                                            : 'bg-white border border-slate-200'
                                                    }`}
                                                >
                                                    {s}
                                                </button>
                                            ))}
                                        </div>
                                        <textarea
                                            value={flag.notes}
                                            onChange={(e) => updateFlag(a.id, { notes: e.target.value })}
                                            placeholder="What's the concern?"
                                            className="w-full rounded-lg border border-slate-300 p-2 text-sm"
                                            rows={2}
                                        />
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
                <button onClick={submitGroup} className="mt-4 w-full rounded-lg bg-brand text-white font-bold py-3">
                    {flagged.length === 0 ? 'All healthy ✓' : `Save (${flagged.length} flagged)`}
                </button>
            </Modal>
        </AppShell>
    );
}
