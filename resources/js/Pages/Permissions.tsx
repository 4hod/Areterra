import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import ModuleHero from '../components/ModuleHero';

interface Preset {
    role: string;
    label: string;
    capabilities: string[];
}

interface PersonRow {
    id: number;
    name: string;
    email: string;
    job_title: string | null;
    role: string;
    capabilities: string[];
}

interface Props {
    catalogue: Record<string, Record<string, string>>;
    presets: Preset[];
    users: PersonRow[];
}

export default function Permissions({ catalogue, presets, users }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(users[0]?.id ?? null);
    const [draft, setDraft] = useState<string[]>(users[0]?.capabilities ?? []);
    const [saving, setSaving] = useState(false);

    const { errors } = usePage().props as unknown as { errors: Record<string, string> };
    const selected = users.find((u) => u.id === selectedId) ?? null;

    const dirty = useMemo(() => {
        if (!selected) return false;
        const a = [...selected.capabilities].sort().join('|');
        const b = [...draft].sort().join('|');
        return a !== b;
    }, [selected, draft]);

    function choose(person: PersonRow) {
        setSelectedId(person.id);
        setDraft(person.capabilities);
    }

    function toggle(capability: string) {
        setDraft((current) =>
            current.includes(capability)
                ? current.filter((c) => c !== capability)
                : [...current, capability],
        );
    }

    function toggleGroup(group: string, on: boolean) {
        const keys = Object.keys(catalogue[group]);
        setDraft((current) =>
            on ? [...new Set([...current, ...keys])] : current.filter((c) => !keys.includes(c)),
        );
    }

    function save() {
        if (!selected) return;
        router.put(`/settings/permissions/${selected.id}`, { capabilities: draft }, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    }

    function applyPreset(role: string) {
        if (!selected) return;
        router.post(`/settings/permissions/${selected.id}/preset`, { role }, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    }

    return (
        <AppShell title="Permissions">
            <Head title="Permissions" />
            <ModuleHero
                eyebrow="Who can do what"
                title="Permissions"
                description="Each person's access is their own. Start from a preset if it helps, then tick or untick anything you need to."
                icon="🔑"
                tone="slate"
            />

            <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
                <Card title="People">
                    <ul className="divide-y divide-slate-200">
                        {users.map((person) => {
                            const active = person.id === selectedId;
                            return (
                                <li key={person.id}>
                                    <button
                                        type="button"
                                        onClick={() => choose(person)}
                                        aria-current={active}
                                        className={`w-full rounded-lg px-3 py-2 text-left transition ${
                                            active ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'
                                        }`}
                                    >
                                        <span className="block text-sm font-medium">{person.name}</span>
                                        <span className={`block text-xs ${active ? 'text-slate-300' : 'text-slate-500'}`}>
                                            {person.job_title || person.email}
                                        </span>
                                        <span className={`block text-xs ${active ? 'text-slate-400' : 'text-slate-400'}`}>
                                            {person.capabilities.length} of {Object.values(catalogue).reduce((n, g) => n + Object.keys(g).length, 0)} permissions
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                </Card>

                {selected ? (
                    <div className="space-y-4">
                        <Card
                            title={selected.name}
                            action={
                                <button
                                    type="button"
                                    onClick={save}
                                    disabled={!dirty || saving}
                                    className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                                >
                                    {saving ? 'Saving…' : 'Save changes'}
                                </button>
                            }
                        >
                            {errors?.capabilities && (
                                <p className="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                    {errors.capabilities}
                                </p>
                            )}

                            <p className="text-sm text-slate-500">
                                Set up from the <strong>{selected.role.replace('_', ' ')}</strong> preset. Changing a
                                preset later does not change anyone already set up — what is ticked here is what{' '}
                                {selected.name.split(' ')[0]} can do.
                            </p>

                            <div className="mt-3 flex flex-wrap gap-2">
                                {presets.map((preset) => (
                                    <button
                                        key={preset.role}
                                        type="button"
                                        onClick={() => applyPreset(preset.role)}
                                        disabled={saving}
                                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 disabled:opacity-40"
                                    >
                                        Start from {preset.label.toLowerCase()}
                                    </button>
                                ))}
                            </div>
                            {dirty && (
                                <p className="mt-3 text-sm text-amber-700">
                                    Unsaved changes. {selected.name.split(' ')[0]} keeps their current access until you save.
                                </p>
                            )}
                        </Card>

                        {Object.entries(catalogue).map(([group, capabilities]) => {
                            const keys = Object.keys(capabilities);
                            const allOn = keys.every((k) => draft.includes(k));

                            return (
                                <Card
                                    key={group}
                                    title={group}
                                    action={
                                        <button
                                            type="button"
                                            onClick={() => toggleGroup(group, !allOn)}
                                            className="text-sm text-slate-500 underline underline-offset-2 hover:text-slate-900"
                                        >
                                            {allOn ? 'Clear all' : 'Select all'}
                                        </button>
                                    }
                                >
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {Object.entries(capabilities).map(([key, label]) => (
                                            <label
                                                key={key}
                                                className="flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-slate-50"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={draft.includes(key)}
                                                    onChange={() => toggle(key)}
                                                    className="mt-1 h-4 w-4 rounded border-slate-300"
                                                />
                                                <span className="text-sm text-slate-700">{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <Card title="Permissions">
                        <p className="text-sm text-slate-500">Choose someone on the left to see what they can do.</p>
                    </Card>
                )}
            </div>
        </AppShell>
    );
}
