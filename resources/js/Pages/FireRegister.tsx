import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import AppShell from '../components/AppShell';

interface MemberRow { id: number; name: string; checked_in_at: string | null }
interface StaffRow { id: number; name: string }

export default function FireRegister({ date, members, staff, autoPrint }: { date: string; members: MemberRow[]; staff: StaffRow[]; autoPrint: boolean }) {
    const total = members.length + staff.length;
    const dateLabel = new Date(`${date}T12:00:00`).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    useEffect(() => {
        if (!autoPrint) return;
        const timer = window.setTimeout(() => window.print(), 250);
        return () => window.clearTimeout(timer);
    }, [autoPrint]);

    const printRegister = (
        <section className="fire-register-print">
            <header><h1>Fire register</h1><p>{dateLabel}</p><p>Printed {new Date().toLocaleString('en-GB')}</p></header>
            <div className="fire-register-summary"><strong>{total}</strong> people recorded on site</div>
            <h2>Members ({members.length})</h2>
            <table><thead><tr><th>Accounted for</th><th>Name</th><th>Arrived</th></tr></thead><tbody>{members.map((member) => <tr key={member.id}><td>☐</td><td>{member.name}</td><td>{member.checked_in_at ?? '—'}</td></tr>)}</tbody></table>
            <h2>Staff ({staff.length})</h2>
            <table><thead><tr><th>Accounted for</th><th>Name</th></tr></thead><tbody>{staff.map((person) => <tr key={person.id}><td>☐</td><td>{person.name}</td></tr>)}</tbody></table>
        </section>
    );

    return <AppShell title="Fire register">
        <Head title="Fire register"/>
        <div className="fire-register-page-4a">
            <section className="register-hero-4a">
                <div><span className="module-kicker-4a">Emergency safety</span><h1>Fire register</h1><p>{dateLabel}</p></div>
                <div className="register-hero-stats-4a"><article><strong>{members.length}</strong><span>Members on site</span></article><article><strong>{staff.length}</strong><span>Staff on site</span></article><article><strong>{total}</strong><span>Total people</span></article></div>
            </section>
            <section className="staff-register-4a fire-register-screen-4a">
                <header><div><span className="module-kicker-4a">Live attendance</span><h2>People currently recorded on site</h2><p>This list is available all day, even while the morning workflow is still in progress.</p></div><button type="button" onClick={() => window.print()} className="module-primary-btn-4a">Print fire register</button></header>
                <div className="staff-register-list-4a">
                    {members.map((member) => <article key={`member-${member.id}`} className="is-present"><div className="staff-register-avatar-4a">M</div><div><b>{member.name}</b><span>Member{member.checked_in_at ? ` · arrived ${member.checked_in_at}` : ''}</span></div><strong>On site</strong></article>)}
                    {staff.map((person) => <article key={`staff-${person.id}`} className="is-present"><div className="staff-register-avatar-4a">S</div><div><b>{person.name}</b><span>Staff</span></div><strong>On site</strong></article>)}
                </div>
                {total === 0 && <div className="module-empty-4a"><span>✓</span><h2>Nobody is recorded on site</h2><p>The list updates from today’s member and staff attendance.</p></div>}
            </section>
            {printRegister}
        </div>
    </AppShell>;
}
