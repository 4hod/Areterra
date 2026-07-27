import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import AppShell from '../../components/AppShell';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { SharedProps } from '../../types';

const DAY_LABELS: Record<number, string> = { 1: 'Mon', 2: 'Tue', 3: 'Wed', 4: 'Thu', 5: 'Fri', 6: 'Sat', 7: 'Sun' };

interface MemberRow {
    id: number;
    name: string;
    status: string;
    attendance_days: number[];
}

const STATUS_TABS = [
    { value: 'all', label: 'All members' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'on-leave', label: 'On leave' },
    { value: 'archived', label: 'Archived' },
];

export default function Index({ members, filters }: { members: MemberRow[]; filters: { search?: string; status?: string } }) {
    const { auth } = usePage<SharedProps>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const status = filters.status ?? 'all';
    const [adding, setAdding] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);
    const canCreate = auth.user?.capabilities.includes('create_members') || auth.user?.role === 'administrator';
    const canEdit = auth.user?.capabilities.includes('edit_members') || auth.user?.role === 'administrator';
    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '', last_name: '', preferred_name: '', status: 'active', attendance_days: [1, 2, 4, 5] as number[],
    });

    const activeCount = useMemo(() => members.filter((m) => m.status === 'active').length, [members]);
    const weeklySessions = useMemo(() => members.reduce((total, member) => total + member.attendance_days.length, 0), [members]);
    const todayDay = new Date().getDay() || 7;
    const attendingToday = useMemo(() => members.filter((m) => m.status === 'active' && m.attendance_days.includes(todayDay)).length, [members, todayDay]);

    function submitNew(e: FormEvent) {
        e.preventDefault();
        post('/members', { onSuccess: () => { setAdding(false); reset(); } });
    }

    function submitSearch(value: string) {
        setSearch(value);
        router.get('/members', { ...(value ? { search: value } : {}), ...(status !== 'all' ? { status } : {}) }, { preserveState: true, replace: true });
    }

    function setStatusFilter(nextStatus: string) {
        router.get('/members', { ...(search ? { search } : {}), ...(nextStatus !== 'all' ? { status: nextStatus } : {}) }, { preserveState: true, replace: true });
    }

    function toggleSelected(id: number) {
        setSelected((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
    }

    function toggleSelectAll() {
        setSelected((current) => current.length === members.length ? [] : members.map((member) => member.id));
    }

    function bulkSetStatus(newStatus: string) {
        router.put('/members/bulk/status', { ids: selected, status: newStatus }, { preserveScroll: true, onSuccess: () => setSelected([]) });
    }

    return (
        <AppShell title="Members">
            <Head title="Members" />
            <div className="members-page-4a">
                <section className="members-hero-4a">
                    <div>
                        <span className="module-kicker-4a">People and support</span>
                        <h1>Members</h1>
                        <p>One clear place for every member, their weekly attendance and current service status.</p>
                    </div>
                    {canCreate && <button onClick={() => setAdding(true)} className="module-primary-btn-4a">+ Add new member</button>}
                </section>

                <section className="members-metrics-4a">
                    <article><span>Active members</span><strong>{activeCount}</strong><small>currently receiving the service</small></article>
                    <article><span>Attending today</span><strong>{attendingToday}</strong><small>based on planned weekly days</small></article>
                    <article><span>Weekly sessions</span><strong>{weeklySessions}</strong><small>planned member attendances</small></article>
                </section>

                <section className="members-toolbar-4a">
                    <div className="members-search-4a"><span>⌕</span><input type="search" value={search} onChange={(e) => submitSearch(e.target.value)} placeholder="Search by member name..." /></div>
                    <div className="members-filters-4a">
                        {STATUS_TABS.map((tab) => <button key={tab.value} onClick={() => setStatusFilter(tab.value)} className={status === tab.value ? 'is-active' : ''}>{tab.label}</button>)}
                    </div>
                </section>

                {canEdit && members.length > 0 && (
                    <section className="members-bulkbar-4a">
                        <label><input type="checkbox" checked={selected.length === members.length} onChange={toggleSelectAll} /> Select all</label>
                        <span>{selected.length ? `${selected.length} selected` : 'Choose members to make a bulk update'}</span>
                        {selected.length > 0 && <div>{STATUS_TABS.filter((tab) => tab.value !== 'all').map((tab) => <button key={tab.value} onClick={() => bulkSetStatus(tab.value)}>Set {tab.label}</button>)}</div>}
                    </section>
                )}

                <section className="members-grid-4a">
                    {members.map((member) => (
                        <article key={member.id} className="member-profile-card-4a">
                            {canEdit && <input className="member-select-4a" type="checkbox" checked={selected.includes(member.id)} onChange={() => toggleSelected(member.id)} aria-label={`Select ${member.name}`} />}
                            <Link href={`/members/${member.id}`} className="member-card-link-4a">
                                <div className="member-card-top-4a">
                                    <div className="member-avatar-4a">{member.name.split(' ').map((part) => part.charAt(0)).join('').slice(0, 2)}</div>
                                    <StatusPill status={member.status} />
                                </div>
                                <div className="member-card-name-4a">{member.name}</div>
                                <div className="member-card-label-4a">Planned attendance</div>
                                <div className="member-days-4a">
                                    {[1, 2, 3, 4, 5, 6, 7].map((day) => <span key={day} className={member.attendance_days.includes(day) ? 'is-planned' : ''}>{DAY_LABELS[day]}</span>)}
                                </div>
                                <div className="member-card-footer-4a"><span>{member.attendance_days.length} days each week</span><b>Open profile →</b></div>
                            </Link>
                        </article>
                    ))}
                </section>

                {members.length === 0 && <div className="module-empty-4a"><span>👥</span><h2>No members found</h2><p>Try changing the search or status filter.</p></div>}
            </div>

            <Modal open={adding} title="Add member" onClose={() => setAdding(false)}>
                <form onSubmit={submitNew} className="space-y-3">
                    <label className="block text-sm font-medium">First name<input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />{errors.first_name && <span className="text-red-600 text-xs">{errors.first_name}</span>}</label>
                    <label className="block text-sm font-medium">Last name<input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required /></label>
                    <label className="block text-sm font-medium">Preferred name (optional)<input value={data.preferred_name} onChange={(e) => setData('preferred_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">Add member</button>
                </form>
            </Modal>
        </AppShell>
    );
}
