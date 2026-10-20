import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '../components/AppShell';
import Modal from '../components/Modal';

interface MemberRow { id: number; name: string; checked_in_at: string | null }
interface StaffRow { id: number; name: string; checked_in_at: string | null }
interface StaffRosterRow { key: string; kind: 'user' | 'roster'; id: number; name: string; job_title: string | null; present: boolean; checked_in_at: string | null; checked_out_at: string | null }
interface VisitorRow { id: number; name: string; organisation: string | null; visiting: string | null; checked_in_at: string; checked_out_at: string | null }

interface Props {
    date: string;
    members: MemberRow[];
    staff: StaffRow[];
    staffRoster: StaffRosterRow[];
    visitors: VisitorRow[];
    checkedOutVisitors: VisitorRow[];
    autoPrint: boolean;
}

export default function FireRegister({ date, members, staff, staffRoster, visitors, checkedOutVisitors, autoPrint }: Props) {
    const [addingVisitor, setAddingVisitor] = useState(false);
    const [showVisitorHistory, setShowVisitorHistory] = useState(false);
    const visitorForm = useForm({ name: '', organisation: '', visiting: '' });
    const total = members.length + staff.length + visitors.length;
    const dateLabel = new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    useEffect(() => {
        if (!autoPrint) return;
        const timer = window.setTimeout(() => window.print(), 250);
        return () => window.clearTimeout(timer);
    }, [autoPrint]);

    function setStaffPresence(person: StaffRosterRow, present: boolean) {
        router.put(`/register/staff/${person.kind}/${person.id}`, { present }, { preserveScroll: true });
    }

    function checkInVisitor() {
        visitorForm.post('/site-visitors', {
            preserveScroll: true,
            onSuccess: () => {
                visitorForm.reset();
                setAddingVisitor(false);
            },
        });
    }

    function checkOutVisitor(visitor: VisitorRow) {
        router.put(`/site-visitors/${visitor.id}/check-out`, {}, { preserveScroll: true });
    }

    const printRegister = (
        <section className="fire-register-print">
            <header><h1>Fire register</h1><p>{dateLabel}</p><p>Printed {new Date().toLocaleString('en-GB')}</p></header>
            <div className="fire-register-summary"><strong>{total}</strong> people recorded on site</div>
            <h2>Members ({members.length})</h2>
            <table><thead><tr><th>Accounted for</th><th>Name</th><th>Arrived</th></tr></thead><tbody>{members.map((member) => <tr key={member.id}><td>☐</td><td>{member.name}</td><td>{member.checked_in_at ?? '—'}</td></tr>)}</tbody></table>
            <h2>Staff ({staff.length})</h2>
            <table><thead><tr><th>Accounted for</th><th>Name</th><th>Arrived</th></tr></thead><tbody>{staff.map((person) => <tr key={person.id}><td>☐</td><td>{person.name}</td><td>{person.checked_in_at ?? '—'}</td></tr>)}</tbody></table>
            <h2>Visitors ({visitors.length})</h2>
            <table><thead><tr><th>Accounted for</th><th>Name</th><th>Organisation / visiting</th><th>Arrived</th></tr></thead><tbody>{visitors.map((visitor) => <tr key={visitor.id}><td>☐</td><td>{visitor.name}</td><td>{[visitor.organisation, visitor.visiting].filter(Boolean).join(' · ') || '—'}</td><td>{visitor.checked_in_at}</td></tr>)}</tbody></table>
        </section>
    );

    return <AppShell title="Fire register">
        <Head title="Fire register"/>
        <div className="fire-register-page-4a">
            <section className="register-hero-4a">
                <div><span className="module-kicker-4a">Emergency safety</span><h1>Fire register</h1><p>{dateLabel}</p></div>
                <div className="register-hero-stats-4a"><article><strong>{members.length}</strong><span>Members on site</span></article><article><strong>{staff.length}</strong><span>Staff on site</span></article><article><strong>{visitors.length}</strong><span>Visitors on site</span></article><article><strong>{total}</strong><span>Total people</span></article></div>
            </section>
            <section className="staff-register-4a fire-register-screen-4a">
                <header><div><span className="module-kicker-4a">Live attendance</span><h2>People currently recorded on site</h2><p>This list is available all day, even while the morning workflow is still in progress.</p></div><button type="button" onClick={() => window.print()} className="module-primary-btn-4a">Print fire register</button></header>
                <div className="staff-register-list-4a">
                    {members.map((member) => <article key={`member-${member.id}`} className="is-present"><div className="staff-register-avatar-4a">M</div><div><b>{member.name}</b><span>Member{member.checked_in_at ? ` · arrived ${member.checked_in_at}` : ''}</span></div><strong>On site</strong></article>)}
                    {staff.map((person) => <article key={`staff-${person.id}`} className="is-present"><div className="staff-register-avatar-4a">S</div><div><b>{person.name}</b><span>Staff{person.checked_in_at ? ` · arrived ${person.checked_in_at}` : ''}</span></div><strong>On site</strong></article>)}
                    {visitors.map((visitor) => <article key={`visitor-${visitor.id}`} className="is-present"><div className="staff-register-avatar-4a">V</div><div><b>{visitor.name}</b><span>{[visitor.organisation, visitor.visiting].filter(Boolean).join(' · ') || 'Visitor'} · arrived {visitor.checked_in_at}</span></div><strong>On site</strong></article>)}
                </div>
                {total === 0 && <div className="module-empty-4a"><span>✓</span><h2>Nobody is recorded on site</h2><p>The list updates from today’s member, staff and visitor attendance.</p></div>}
            </section>

            <section className="staff-register-4a">
                <header><div><span className="module-kicker-4a">Arrivals and departures</span><h2>Staff and visitors</h2><p>Keep this current whenever somebody arrives or leaves, so the fire register always matches who is on site.</p></div><button type="button" onClick={() => setAddingVisitor(true)} className="module-primary-btn-4a">+ Check in visitor</button></header>
                <div className="staff-register-list-4a">
                    {staffRoster.map((person) => <article key={person.key} className={person.present ? 'is-present' : ''}><div className="staff-register-avatar-4a">S</div><div><b>{person.name}</b><span>{person.present ? `Arrived ${person.checked_in_at ?? 'today'}` : person.checked_out_at ? `Left ${person.checked_out_at}` : (person.job_title ?? 'Staff')}</span></div><button type="button" onClick={() => setStaffPresence(person, !person.present)} className={person.present ? 'is-present' : ''}>{person.present ? 'Check out' : 'Check in'}</button></article>)}
                    {visitors.map((visitor) => <article key={`visitor-action-${visitor.id}`} className="is-present"><div className="staff-register-avatar-4a">V</div><div><b>{visitor.name}</b><span>Visitor · arrived {visitor.checked_in_at}</span></div><button type="button" onClick={() => checkOutVisitor(visitor)} className="is-present">Check out</button></article>)}
                </div>
            </section>

            {checkedOutVisitors.length > 0 && <section className="register-others-4a"><button type="button" onClick={() => setShowVisitorHistory(!showVisitorHistory)}><span>Visitors checked out today</span><b>{checkedOutVisitors.length}</b><i>{showVisitorHistory ? 'Hide' : 'Show'} history</i></button>{showVisitorHistory && <div>{checkedOutVisitors.map((visitor) => <article key={`visitor-history-${visitor.id}`}><span><strong className="block text-sm text-brand-dark">{visitor.name}</strong><small className="block mt-0.5 text-slate-500">Arrived {visitor.checked_in_at} · left {visitor.checked_out_at}</small></span></article>)}</div>}</section>}

            {printRegister}

            <Modal open={addingVisitor} title="Check in visitor" onClose={() => setAddingVisitor(false)}>
                <form onSubmit={(event) => { event.preventDefault(); checkInVisitor(); }} className="space-y-4">
                    <div><label htmlFor="visitor-name" className="mb-1 block text-sm font-semibold">Visitor name</label><input id="visitor-name" value={visitorForm.data.name} onChange={(event) => visitorForm.setData('name', event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2" required autoFocus />{visitorForm.errors.name && <p className="mt-1 text-xs text-red-700">{visitorForm.errors.name}</p>}</div>
                    <div><label htmlFor="visitor-organisation" className="mb-1 block text-sm font-semibold">Organisation <span className="font-normal text-slate-500">(optional)</span></label><input id="visitor-organisation" value={visitorForm.data.organisation} onChange={(event) => visitorForm.setData('organisation', event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2" /></div>
                    <div><label htmlFor="visitor-visiting" className="mb-1 block text-sm font-semibold">Who or what are they visiting? <span className="font-normal text-slate-500">(optional)</span></label><input id="visitor-visiting" value={visitorForm.data.visiting} onChange={(event) => visitorForm.setData('visiting', event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2" /></div>
                    <button type="submit" disabled={visitorForm.processing} className="w-full rounded-lg bg-brand py-3 font-bold text-white disabled:opacity-50">{visitorForm.processing ? 'Checking in…' : 'Check in visitor'}</button>
                </form>
            </Modal>
        </div>
    </AppShell>;
}
