import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { welfareStatusLabel } from '../../utils/welfare';
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
    monitored_today: boolean;
    concern_today: boolean;
    next_vet_due: string | null;
}

interface Summary {
    total: number;
    checked: number;
    monitored: number;
    concerns: number;
    vet_due: number;
}

type CareFilter = 'all' | 'outstanding' | 'concerns' | 'complete';

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

export default function Index({ species, bySpecies, summary, canEdit }: { species: string[]; bySpecies: Record<string, AnimalRow[]>; summary: Summary; canEdit: boolean }) {
    const [checking, setChecking] = useState<string | null>(null);
    const [checks, setChecks] = useState<Record<number, AnimalCheck>>({});
    const [verificationConfirmed, setVerificationConfirmed] = useState(false);
    const [adding, setAdding] = useState(false);
    const [filter, setFilter] = useState<CareFilter>('all');
    const [search, setSearch] = useState('');
    const [newAnimal, setNewAnimal] = useState({ name: '', species: species[0] ?? '', sex: 'unknown', joined_date: '', care_requirements: '', feeding_notes: '' });

    const visibleSpecies = useMemo(() => Object.entries(bySpecies)
        .map(([speciesName, animals]) => [speciesName, animals.filter((animal) => {
            const matchesSearch = !search.trim() || `${animal.name} ${animal.species}`.toLowerCase().includes(search.trim().toLowerCase());
            const matchesFilter = filter === 'all'
                || (filter === 'outstanding' && (!animal.checked_today || !animal.monitored_today))
                || (filter === 'concerns' && (animal.concern_today || animal.welfare_status !== 'green'))
                || (filter === 'complete' && animal.checked_today && animal.monitored_today && !animal.concern_today);

            return matchesSearch && matchesFilter;
        })] as const)
        .filter(([, animals]) => animals.length > 0), [bySpecies, filter, search]);

    const firstOutstandingSpecies = Object.entries(bySpecies).find(([, animals]) => animals.some((animal) => !animal.checked_today))?.[0] ?? null;
    const welfarePct = summary.total ? Math.round((summary.checked / summary.total) * 100) : 100;
    const monitoringPct = summary.total ? Math.round((summary.monitored / summary.total) * 100) : 100;

    function openSpeciesCheck(speciesName: string) {
        setChecking(speciesName);
        setChecks({});
        setVerificationConfirmed(false);
    }

    function addAnimal() {
        router.post('/animals', { ...newAnimal, status: 'active' }, {
            onSuccess: () => {
                setAdding(false);
                setNewAnimal({ name: '', species: species[0] ?? '', sex: 'unknown', joined_date: '', care_requirements: '', feeding_notes: '' });
            },
        });
    }

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
                verification_confirmed: verificationConfirmed,
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
                    setVerificationConfirmed(false);
                },
            },
        );
    }

    return (
        <AppShell title="Animals">
            <Head title="Animals" />
            <div className="animals-heading-actions-4a">
                <ModuleHero eyebrow="Animal care" title="Today's animal care" description="See what needs doing, record care and spot concerns early." icon="🐾" tone="green" />

                {canEdit && (
                    <button onClick={() => setAdding(true)} className="animals-add-quiet-4a">
                        + Add animal record
                    </button>
                )}
            </div>

            <section className="animal-care-board-5a">
                <div className="animal-care-board-head-5a">
                    <div>
                        <span>DAILY CARE BOARD</span>
                        <h2>{summary.checked === summary.total && summary.monitored === summary.total ? 'Today’s care is complete' : 'Keep today’s care moving'}</h2>
                        <p>{summary.total} active animal{summary.total === 1 ? '' : 's'} · updates appear here as staff record them</p>
                    </div>
                    <div className="animal-care-board-actions-5a">
                        {firstOutstandingSpecies && <button onClick={() => openSpeciesCheck(firstOutstandingSpecies)}>Continue welfare checks</button>}
                        <Link href="/monitoring">Open daily monitoring</Link>
                    </div>
                </div>
                <div className="animal-care-progress-5a">
                    <div><span><b>Welfare & feeding</b><em>{summary.checked}/{summary.total}</em></span><i><u style={{ width: `${welfarePct}%` }} /></i><small>{summary.total - summary.checked === 0 ? 'All recorded' : `${summary.total - summary.checked} outstanding`}</small></div>
                    <div><span><b>Daily monitoring</b><em>{summary.monitored}/{summary.total}</em></span><i><u style={{ width: `${monitoringPct}%` }} /></i><small>{summary.total - summary.monitored === 0 ? 'All recorded' : `${summary.total - summary.monitored} outstanding`}</small></div>
                    <div className={summary.concerns ? 'has-alert' : ''}><span><b>Needs attention</b><em>{summary.concerns}</em></span><p>{summary.concerns ? 'Open the highlighted animal records' : 'No concerns recorded today'}</p></div>
                    <div className={summary.vet_due ? 'has-due' : ''}><span><b>Vet care due</b><em>{summary.vet_due}</em></span><p>{summary.vet_due ? 'Due or coming up within 30 days' : 'Nothing due in the next 30 days'}</p></div>
                </div>
            </section>

            <div className="animal-care-tools-5a">
                <label><span className="sr-only">Search animals</span><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search animals…" /></label>
                <div role="group" aria-label="Filter animal care records">
                    {([
                        ['all', 'All'],
                        ['outstanding', 'Outstanding'],
                        ['concerns', 'Concerns'],
                        ['complete', 'Complete'],
                    ] as [CareFilter, string][]).map(([value, label]) => <button key={value} onClick={() => setFilter(value)} className={filter === value ? 'is-active' : ''}>{label}</button>)}
                </div>
            </div>

            <div className="space-y-4">
                {visibleSpecies.map(([species, animals]) => {
                    const allChecked = animals.every((a) => a.checked_today);
                    return (
                        <Card
                            key={species}
                            title={`${SPECIES_EMOJI[species] ?? '🐾'} ${species}s (${animals.length})`}
                            action={
                                <button
                                    onClick={() => openSpeciesCheck(species)}
                                    className={`rounded-full text-xs font-bold px-3 py-1.5 ${
                                        allChecked ? 'bg-emerald-100 text-emerald-800' : 'bg-brand text-white'
                                    }`}
                                >
                                    {allChecked ? '✓ Review today\'s checks' : 'Open today\'s checks'}
                                </button>
                            }
                        >
                            <div className="flex flex-wrap gap-2">
                                {animals.map((a) => (
                                    <Link
                                        key={a.id}
                                        href={`/animals/${a.id}`}
                                        className={`animal-list-link-4a animal-care-row-5a ${a.concern_today || a.welfare_status !== 'green' ? 'has-concern' : ''}`}
                                    >
                                        <span className="animal-care-row-main-5a"><strong>{a.name}</strong><small>{a.next_vet_due ? `Next vet care ${new Date(a.next_vet_due).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}` : 'No upcoming vet care recorded'}</small></span>
                                        <span className="animal-care-row-statuses-5a">
                                            <small className={a.checked_today ? 'is-done' : 'is-pending'}>{a.checked_today ? '✓ Welfare recorded' : 'Welfare due'}</small>
                                            <small className={a.monitored_today ? 'is-done' : 'is-pending'}>{a.monitored_today ? '✓ Monitoring recorded' : 'Monitoring due'}</small>
                                        </span>
                                        <em className={`is-${a.welfare_status}`}>{welfareStatusLabel(a.welfare_status)}</em>
                                    </Link>
                                ))}
                            </div>
                        </Card>
                    );
                })}
                {visibleSpecies.length === 0 && <div className="animal-care-empty-5a"><span>🐾</span><b>No animals match this view</b><p>Try another filter or clear the search.</p></div>}
            </div>

            <Modal open={adding} title="Add animal" onClose={() => setAdding(false)}>
                <div className="space-y-3">
                    <label className="block text-sm font-medium">
                        Name
                        <input value={newAnimal.name} onChange={(e) => setNewAnimal({ ...newAnimal, name: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Species
                            <select value={newAnimal.species} onChange={(e) => setNewAnimal({ ...newAnimal, species: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3">
                                {species.map((item) => <option key={item} value={item}>{item}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Sex
                            <select value={newAnimal.sex} onChange={(e) => setNewAnimal({ ...newAnimal, sex: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3">
                                <option value="unknown">Unknown</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Joined Areterra
                        <input type="date" value={newAnimal.joined_date} onChange={(e) => setNewAnimal({ ...newAnimal, joined_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Care requirements
                        <textarea value={newAnimal.care_requirements} onChange={(e) => setNewAnimal({ ...newAnimal, care_requirements: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Feeding
                        <textarea value={newAnimal.feeding_notes} onChange={(e) => setNewAnimal({ ...newAnimal, feeding_notes: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button disabled={!newAnimal.name.trim() || !newAnimal.species} onClick={addAnimal} className="w-full rounded-lg bg-brand py-3 font-bold text-white disabled:opacity-50">
                        Add animal
                    </button>
                </div>
            </Modal>

            <Modal open={checking !== null} title={`${checking} daily check`} onClose={() => setChecking(null)}>
                <p className="text-sm text-slate-500 mb-4">
                    Review every animal below. The form starts at healthy and fed, but nothing is saved until you confirm you personally checked the full group.
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
                                                    {s === 'amber' ? 'Keep watching' : 'Urgent concern'}
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
                <label className="mt-4 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm font-medium">
                    <input type="checkbox" checked={verificationConfirmed} onChange={(e) => setVerificationConfirmed(e.target.checked)} className="mt-0.5 h-4 w-4" />
                    <span>I confirm I personally checked every animal listed above and the feeding status shown is accurate.</span>
                </label>
                <button disabled={!verificationConfirmed} onClick={submitGroup} className="mt-3 w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-50">
                    {exceptionCount() === 0 ? 'All healthy & fed ✓' : `Save (${exceptionCount()} noted)`}
                </button>
            </Modal>
        </AppShell>
    );
}
