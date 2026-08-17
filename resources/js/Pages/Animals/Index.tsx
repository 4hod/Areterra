import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { WelfareStatus } from '../../types';
import ModuleHero from '../../components/ModuleHero';

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

interface AnimalCheck {
    animal_id: number;
    concern: boolean;
    status: 'amber' | 'red';
    notes: string;
    fed: boolean;
    treats_given: boolean;
    treats_notes: string;
}

function blankCheck(animal_id: number): AnimalCheck {
    return { animal_id, concern: false, status: 'amber', notes: '', fed: true, treats_given: false, treats_notes: '' };
}

export default function Index({ bySpecies }: { species: string[]; bySpecies: Record<string, AnimalRow[]> }) {
    const [checking, setChecking] = useState<string | null>(null);
    const [checks, setChecks] = useState<Record<number, AnimalCheck>>({});

    function getCheck(id: number): AnimalCheck {
        return checks[id] ?? blankCheck(id);
    }

    function updateCheck(id: number, patch: Partial<AnimalCheck>) {
        setChecks((c) => ({ ...c, [id]: { ...getCheck(id), ...patch } }));
    }

    function toggleConcern(id: number) {
        updateCheck(id, { concern: !getCheck(id).concern });
    }

    function toggleNotFed(id: number) {
        updateCheck(id, { fed: !getCheck(id).fed });
    }

    function toggleTreats(id: number) {
        updateCheck(id, { treats_given: !getCheck(id).treats_given });
    }

    function exceptionCount() {
        return Object.values(checks).filter((c) => c.concern || !c.fed || c.treats_given).length;
    }

    function submitGroup() {
        if (!checking) return;
        const animals = bySpecies[checking] ?? [];
        router.post(
            '/welfare-checks/species',
            {
                species: checking,
                checks: animals.map((a) => {
                    const c = getCheck(a.id);
                    return {
                        animal_id: a.id,
                        status: c.concern ? c.status : null,
                        notes: c.concern ? c.notes : null,
                        fed: c.fed,
                        treats_given: c.treats_given,
                        treats_notes: c.treats_given ? c.treats_notes : null,
                    };
                }),
            },
            {
                onSuccess: () => {
                    setChecking(null);
                    setChecks({});
                },
            },
        );
    }

    return (
        <AppShell title="Animals">
            <Head title="Animals" />
            <ModuleHero eyebrow="Animal care" title="Animals" description="See every animal, their care status and what needs attention today." icon="🦜" tone="green" />

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
                                        setChecks({});
                                    }}
                                    className={`rounded-full text-xs font-bold px-3 py-1.5 ${
                                        allChecked ? 'bg-emerald-100 text-emerald-800' : 'bg-brand text-white'
                                    }`}
                                >
                                    {allChecked ? '✓ Checked today' : 'Daily check'}
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

            <Modal open={checking !== null} title={`${checking} daily check`} onClose={() => setChecking(null)}>
                <p className="text-sm text-slate-500 mb-4">
                    Everyone is recorded as healthy and fed unless you note otherwise below.
                </p>
                <div className="space-y-3">
                    {(bySpecies[checking ?? ''] ?? []).map((a) => {
                        const c = getCheck(a.id);
                        const hasException = c.concern || !c.fed || c.treats_given;
                        return (
                            <div key={a.id} className={`rounded-lg border p-3 ${hasException ? 'border-amber-300 bg-amber-50' : 'border-slate-200'}`}>
                                <div className="flex items-center justify-between mb-2">
                                    <span className="font-semibold">{a.name}</span>
                                </div>

                                <div className="flex flex-wrap gap-1.5">
                                    <button
                                        onClick={() => toggleConcern(a.id)}
                                        className={`text-xs font-bold rounded-full px-3 py-1.5 ${
                                            c.concern ? 'bg-amber-200 text-amber-900' : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {c.concern ? '⚠ Flagged' : 'Flag concern'}
                                    </button>
                                    <button
                                        onClick={() => toggleNotFed(a.id)}
                                        className={`text-xs font-bold rounded-full px-3 py-1.5 ${
                                            !c.fed ? 'bg-red-200 text-red-900' : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {c.fed ? '🍽️ Fed' : '🚫 Not fed'}
                                    </button>
                                    <button
                                        onClick={() => toggleTreats(a.id)}
                                        className={`text-xs font-bold rounded-full px-3 py-1.5 ${
                                            c.treats_given ? 'bg-emerald-200 text-emerald-900' : 'bg-slate-100 text-slate-500'
                                        }`}
                                    >
                                        {c.treats_given ? '🍪 Treats given' : 'No treats'}
                                    </button>
                                </div>

                                {c.concern && (
                                    <div className="mt-2 space-y-2">
                                        <div className="flex gap-2">
                                            {(['amber', 'red'] as const).map((s) => (
                                                <button
                                                    key={s}
                                                    onClick={() => updateCheck(a.id, { status: s })}
                                                    className={`flex-1 rounded-lg py-2 text-sm font-bold capitalize ${
                                                        c.status === s
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
                                            value={c.notes}
                                            onChange={(e) => updateCheck(a.id, { notes: e.target.value })}
                                            placeholder="What's the concern?"
                                            className="w-full rounded-lg border border-slate-300 p-2 text-sm"
                                            rows={2}
                                        />
                                    </div>
                                )}

                                {c.treats_given && (
                                    <input
                                        value={c.treats_notes}
                                        onChange={(e) => updateCheck(a.id, { treats_notes: e.target.value })}
                                        placeholder="What treats? (optional)"
                                        className="mt-2 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>
                <button onClick={submitGroup} className="mt-4 w-full rounded-lg bg-brand text-white font-bold py-3">
                    {exceptionCount() === 0 ? 'All healthy & fed ✓' : `Save (${exceptionCount()} noted)`}
                </button>
            </Modal>
        </AppShell>
    );
}
