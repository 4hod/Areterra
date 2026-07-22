import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import SegmentedControl from '../../components/SegmentedControl';
import EmptyState from '../../components/EmptyState';
import { SharedProps } from '../../types';

const DAY_LABELS: Record<number, string> = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' };

interface MemberRow {
    id: number;
    name: string;
    status: string;
    attendance_days: number[];
}

export default function Index({ members, filters }: { members: MemberRow[]; filters: { search?: string; status?: string } }) {
    const { auth } = usePage<SharedProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const status = filters.status ?? 'all';
    const [adding, setAdding] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const canCreate = auth.user?.capabilities.includes('create_members') || auth.user?.role === 'administrator';
    const canEdit = auth.user?.capabilities.includes('edit_members') || auth.user?.role === 'administrator';
    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        preferred_name: '',
        status: 'active',
        attendance_days: [1, 2, 4, 5] as number[],
    });

    function submitNew(e: FormEvent) {
        e.preventDefault();
        post('/members', {
            onSuccess: () => {
                setAdding(false);
                reset();
            },
        });
    }

    function submitSearch(value: string) {
        setSearch(value);
        router.get('/members', { ...(value ? { search: value } : {}), ...(status !== 'all' ? { status } : {}) }, { preserveState: true, replace: true });
    }

    function setStatusFilter(s: string) {
        router.get('/members', { ...(search ? { search } : {}), ...(s !== 'all' ? { status: s } : {}) }, { preserveState: true, replace: true });
    }

    function toggleSelected(id: number) {
        setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    }

    function toggleSelectAll() {
        setSelected((s) => (s.length === members.length ? [] : members.map((m) => m.id)));
    }

    function bulkSetStatus(newStatus: string) {
        router.put('/members/bulk/status', { ids: selected, status: newStatus }, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    }

    const STATUS_TABS = [
        { value: 'all', label: 'All' },
        { value: 'active', label: 'Active' },
        { value: 'inactive', label: 'Inactive' },
        { value: 'on-leave', label: 'On leave' },
        { value: 'archived', label: 'Archived' },
    ];

    return (
        <AppShell title="Members">
            <Head title="Members" />

            <div className="flex gap-2 mb-4">
                <input
                    type="search"
                    value={search}
                    onChange={(e) => submitSearch(e.target.value)}
                    placeholder="Search members…"
                    className="flex-1 rounded-lg border border-slate-300 px-3 bg-white"
                />
                {canCreate && (
                    <button
                        onClick={() => setAdding(true)}
                        className="shrink-0 rounded-lg bg-brand text-white font-semibold px-4 flex items-center"
                    >
                        + Add
                    </button>
                )}
            </div>

            <SegmentedControl options={STATUS_TABS} value={status} onChange={setStatusFilter} />

            {canEdit && members.length > 0 && (
                <div className="flex items-center gap-3 mb-2 px-1">
                    <label className="flex items-center gap-2 text-xs font-semibold text-ink/50">
                        <input
                            type="checkbox"
                            checked={selected.length === members.length}
                            onChange={toggleSelectAll}
                            className="rounded border-slate-300"
                        />
                        Select all
                    </label>
                    {selected.length > 0 && (
                        <div className="flex items-center gap-1.5 ml-auto">
                            <span className="text-xs text-ink/45">{selected.length} selected</span>
                            {STATUS_TABS.filter((t) => t.value !== 'all').map((t) => (
                                <button
                                    key={t.value}
                                    onClick={() => bulkSetStatus(t.value)}
                                    className="rounded-full bg-ink/[0.06] text-ink/70 text-xs font-bold px-3 py-1.5 hover:bg-ink/10"
                                >
                                    Set {t.label}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <div className="space-y-2">
                {members.map((m) => (
                    <div key={m.id} className="flex items-center gap-2">
                        {canEdit && (
                            <input
                                type="checkbox"
                                checked={selected.includes(m.id)}
                                onChange={() => toggleSelected(m.id)}
                                className="shrink-0 rounded border-slate-300"
                                aria-label={`Select ${m.name}`}
                            />
                        )}
                        <Link href={`/members/${m.id}`} className="block flex-1 min-w-0">
                            <Card>
                                <div className="flex items-center gap-3">
                                    <div className="h-11 w-11 shrink-0 rounded-full bg-brand/10 text-brand font-bold flex items-center justify-center text-lg">
                                        {m.name.charAt(0)}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="font-bold text-brand-dark truncate">{m.name}</div>
                                        <div className="text-xs text-slate-400 font-medium">
                                            {m.attendance_days.map((d) => DAY_LABELS[d]).join(' · ')}
                                        </div>
                                    </div>
                                    <StatusPill status={m.status} />
                                </div>
                            </Card>
                        </Link>
                    </div>
                ))}
                {members.length === 0 && (
                    <Card>
                        <EmptyState icon="👥" text="No members found." />
                    </Card>
                )}
            </div>

            <Modal open={adding} title="Add member" onClose={() => setAdding(false)}>
                <form onSubmit={submitNew} className="space-y-3">
                    <label className="block text-sm font-medium">
                        First name
                        <input
                            value={data.first_name}
                            onChange={(e) => setData('first_name', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            required
                        />
                        {errors.first_name && <span className="text-red-600 text-xs">{errors.first_name}</span>}
                    </label>
                    <label className="block text-sm font-medium">
                        Last name
                        <input
                            value={data.last_name}
                            onChange={(e) => setData('last_name', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            required
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Preferred name (optional)
                        <input
                            value={data.preferred_name}
                            onChange={(e) => setData('preferred_name', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Add member
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
