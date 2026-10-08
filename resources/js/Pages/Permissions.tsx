import { Head, router, useForm, usePage } from '@inertiajs/react';
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
    working_days: number[];
}

interface Props {
    catalogue: Record<string, Record<string, string>>;
    presets: Preset[];
    users: PersonRow[];
}

export default function Permissions({ catalogue, presets, users }: Props) {
    const accountForm = useForm({ name: '', email: '', job_title: '', role: 'staff', working_days: [1, 2, 4, 5] as number[] });
    const [selectedId, setSelectedId] = useState<number | null>(users[0]?.id ?? null);
    const [draft, setDraft] = useState<string[]>(users[0]?.capabilities ?? []);
    const [saving, setSaving] = useState(false);
    const [customising, setCustomising] = useState(false);
    const [workingDays, setWorkingDays] = useState<number[]>(users[0]?.working_days ?? [1, 2, 4, 5]);

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
        setCustomising(false);
        setWorkingDays(person.working_days);
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

            <Card title="Add staff account" className="mb-4">
                <form onSubmit={(event) => { event.preventDefault(); accountForm.post('/settings/users', { onSuccess: () => accountForm.reset() }); }} className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="text-sm font-medium">Name<input required value={accountForm.data.name} onChange={(e) => accountForm.setData('name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label>
                    <label className="text-sm font-medium">Microsoft email<input required type="email" value={accountForm.data.email} onChange={(e) => accountForm.setData('email', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label>
                    <label className="text-sm font-medium">Job title<input value={accountForm.data.job_title} onChange={(e) => accountForm.setData('job_title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label>
                    <label className="text-sm font-medium">Starting role<select value={accountForm.data.role} onChange={(e) => accountForm.setData('role', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3">{presets.map((preset) => <option key={preset.role} value={preset.role}>{preset.label}</option>)}</select></label>
                    <fieldset className="sm:col-span-2 lg:col-span-4"><legend className="text-sm font-medium">Normal working days</legend><div className="mt-1 flex flex-wrap gap-2">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day, index) => { const value = index + 1; return <label key={day} className="flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-sm"><input type="checkbox" checked={accountForm.data.working_days.includes(value)} onChange={(e) => accountForm.setData('working_days', e.target.checked ? [...accountForm.data.working_days, value].sort() : accountForm.data.working_days.filter((item) => item !== value))} />{day}</label>; })}</div></fieldset>
                    <button disabled={accountForm.processing} className="rounded-lg bg-brand px-4 py-2 font-bold text-white sm:col-span-2 lg:col-span-4 disabled:opacity-50">Create account</button>
                </form>
            </Card>

            <label className="permissions-person-picker-4a"><span>Choose a person</span><select value={selectedId ?? ''} onChange={(event) => { const person = users.find((user) => user.id === Number(event.target.value)); if (person) choose(person); }}>{users.map((person) => <option key={person.id} value={person.id}>{person.name} — {person.job_title || person.role}</option>)}</select></label>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
                <Card title="People" className="permissions-people-card-4a">
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
                            <button type="button" onClick={() => window.confirm(`Deactivate ${selected.name}'s Hub account?`) && router.delete(`/settings/users/${selected.id}`)} className="float-right ml-3 rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">Deactivate account</button>
                            {errors?.capabilities && (
                                <p className="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                                    {errors.capabilities}
                                </p>
                            )}

                            <p className="text-sm text-slate-500">
                                Current role: <strong>{selected.role.replace('_', ' ')}</strong>. Choose a straightforward role preset, or open custom permissions only when this person needs an exception.
                            </p>

                            <div className="mt-4 rounded-xl border border-slate-200 p-3">
                                <div className="flex items-center justify-between gap-3">
                                    <div><div className="text-sm font-bold">Normal working days</div><div className="text-xs text-slate-500">Used to calculate leave correctly.</div></div>
                                    <button type="button" onClick={() => router.put(`/settings/users/${selected.id}`, { job_title: selected.job_title, working_days: workingDays }, { preserveScroll: true })} disabled={workingDays.length === 0} className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold disabled:opacity-40">Save days</button>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((day, index) => { const value = index + 1; return <label key={day} className="flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs"><input type="checkbox" checked={workingDays.includes(value)} onChange={(e) => setWorkingDays((current) => e.target.checked ? [...current, value].sort() : current.filter((item) => item !== value))} />{day}</label>; })}</div>
                            </div>

                            <div className="permissions-presets-4a">
                                {presets.map((preset) => (
                                    <button
                                        key={preset.role}
                                        type="button"
                                        onClick={() => applyPreset(preset.role)}
                                        disabled={saving}
                                        className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 disabled:opacity-40"
                                    >
                                        {preset.label}
                                    </button>
                                ))}
                            </div>
                            <button type="button" className="permissions-custom-toggle-4a" onClick={() => setCustomising((value) => !value)} aria-expanded={customising}>{customising ? 'Hide custom permissions' : 'Custom permissions'} <span>{customising ? '−' : '+'}</span></button>
                            {dirty && (
                                <p className="mt-3 text-sm text-amber-700">
                                    Unsaved changes. {selected.name.split(' ')[0]} keeps their current access until you save.
                                </p>
                            )}
                        </Card>

                        {customising && Object.entries(catalogue).map(([group, capabilities]) => {
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
