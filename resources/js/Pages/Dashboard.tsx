import { Head, Link, usePage } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import AppIcon from '../components/AppIcon';
import { ChecklistItem, SharedProps } from '../types';

interface Notice { kind: string; message: string; due_on: string | null; overdue: boolean }
interface Props {
    needsAttention: Notice[];
    orgIsEmpty: boolean;
    birthdays: { id: number; name: string; date: string; is_today: boolean }[];
    stats: {
        memberRecords: number;
        membersInToday: number;
        membersScheduled: number;
        animalsChecked: number;
        animalsTotal: number;
        animalsNeedingChecks: number;
        attendanceTrend: number[];
    };
    welfareAlerts: { id: number; name: string; species: string; welfare_status: string }[];
    checklist: ChecklistItem[];
    banner: string | null;
    staffAvatars: string[];
    leaveBalance: { entitlement: number; taken: number; remaining: number };
    announcements: { id: number; title: string; author: string; created_at: string; read: boolean }[];
    notifications: { id: string; title: string; body: string; url: string; read: boolean; created_at: string }[];
}

function greeting() {
    const hour = new Date().getHours();
    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}

function Metric({ icon, label, value, detail, tone = 'blue' }: { icon: string; label: string; value: string | number; detail: string; tone?: string }) {
    return <article className="overview-metric"><span className={`is-${tone}`}><AppIcon name={icon}/></span><div><small>{label}</small><strong>{value}</strong><p>{detail}</p></div></article>;
}

function PanelTitle({ icon, title, count, href, action }: { icon: string; title: string; count?: string; href?: string; action?: string }) {
    return <header className="overview-panel-title"><div><AppIcon name={icon}/><h2>{title}</h2></div>{count && <span>{count}</span>}{href && <Link href={href}>{action ?? 'Open'} <AppIcon name="arrow"/></Link>}</header>;
}

const quickActions = [
    { href: '/register', label: 'Take the register', icon: 'register', caps: ['view_member_details', 'log_sessions'] },
    { href: '/incidents', label: 'Report an incident', icon: 'alert', caps: ['report_incidents'] },
    { href: '/documents', label: 'Open documents', icon: 'folder', caps: ['view_documents'] },
    { href: '/leave', label: 'Request leave', icon: 'leave', caps: ['request_leave'] },
];

export default function Dashboard(props: Props) {
    const { auth } = usePage<SharedProps>().props;
    const capabilities = auth.user?.capabilities ?? [];
    const hasAll = (...required: string[]) => required.every((capability) => capabilities.includes(capability));
    const canUseRegister = hasAll('view_member_details', 'log_sessions');
    const canViewMemberDetails = hasAll('view_member_details');
    const availableQuickActions = quickActions.filter((action) => hasAll(...action.caps));
    const firstName = auth.user?.name?.split(' ')[0] ?? 'there';
    const completed = props.checklist.filter((item) => item.done).length;
    const openTasks = props.checklist.length - completed;
    const reviewNotices = props.needsAttention.filter((notice) => notice.kind.toLowerCase().includes('review'));
    const priorities = [
        ...(props.stats.animalsNeedingChecks > 0 ? [{ kind: 'welfare', message: `${props.stats.animalsNeedingChecks} animal welfare check${props.stats.animalsNeedingChecks === 1 ? '' : 's'} still need recording.`, due_on: null, overdue: false }] : []),
        ...props.needsAttention,
        ...(openTasks > 0 ? [{ kind: 'workflow', message: `${openTasks} step${openTasks === 1 ? '' : 's'} in today’s workflow remain open.`, due_on: null, overdue: false }] : []),
    ].slice(0, 4);
    const currentDate = new Date();
    const dayName = currentDate.toLocaleDateString('en-GB', { weekday: 'long' });
    const dateLine = currentDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });

    if (props.orgIsEmpty) return <AppShell title="Dashboard"><Head title="Dashboard"/><section className="overview-empty"><AppIcon name="paw"/><span>YOUR NEW WORKSPACE</span><h1>Welcome to Areterra Hub, {firstName}.</h1><p>Add your first members and animals to bring attendance, welfare and daily operations to life.</p><div>{capabilities.includes('create_members') && <Link href="/members">Add a member</Link>}{capabilities.includes('edit_animals') && <Link href="/animals">Add an animal</Link>}</div></section></AppShell>;

    return <AppShell title="Dashboard">
        <Head title="Dashboard"/>
        <div className="overview-page">
            {props.banner && <div className="overview-banner"><AppIcon name="megaphone"/>{props.banner}</div>}

            <section className="overview-intro">
                <div className="overview-intro-copy"><span>ARETERRA HUB</span><h1>{dayName} <i aria-hidden="true">☀</i></h1><h2>{dateLine}</h2><p>Your daily view of tasks, animal care and team activity.</p></div>
                <div className="overview-intro-motto"><b>Support</b><b>Nurture</b><b>Belong</b><i/></div>
            </section>

            <section className="overview-metrics">
                <Metric icon="users" label="Member records" value={props.stats.memberRecords} detail="Support at a glance"/>
                <Metric icon="paw" label="Welfare checks" value={`${props.stats.animalsChecked} / ${props.stats.animalsTotal}`} detail={props.stats.animalsNeedingChecks ? `${props.stats.animalsNeedingChecks} checks outstanding` : 'All checks recorded'} tone="yellow"/>
                <Metric icon="tasks" label="Daily workflow" value={`${completed} / ${props.checklist.length}`} detail={openTasks ? `${openTasks} actions remaining` : 'Everything complete'}/>
                <Metric icon="shield" label="Staff on site" value={props.staffAvatars.length} detail="From the morning register"/>
            </section>

            <section className="overview-top-grid">
                <article className="overview-panel overview-priorities">
                    <PanelTitle icon="activity" title="Needs your attention" count={`${priorities.length} priorit${priorities.length === 1 ? 'y' : 'ies'}`}/>
                    {priorities.length ? <div className="overview-priority-list">{priorities.map((notice, index) => <div key={`${notice.kind}-${index}`}>
                        <span className={notice.kind === 'welfare' ? 'is-yellow' : notice.overdue ? 'is-red' : ''}><AppIcon name={notice.kind === 'welfare' ? 'paw' : notice.overdue ? 'alert' : 'file'}/></span>
                        <div><b>{notice.kind.replace(/_/g, ' ')}</b><p>{notice.message}</p></div>
                        <div className="overview-priority-meta">{notice.due_on && <time>{new Date(notice.due_on).toLocaleDateString('en-GB')}</time>}<AppIcon name="arrow"/></div>
                    </div>)}</div> : <div className="overview-all-clear"><AppIcon name="shield"/><div><b>Everything is up to date</b><p>There are no current priorities requiring attention.</p></div></div>}
                </article>

                <article className="overview-panel overview-register-panel">
                    <PanelTitle icon="register" title="Fire register" count={props.stats.membersInToday || props.staffAvatars.length ? 'Live' : 'Not started'}/>
                    <div className="overview-register-numbers"><div><strong>{props.stats.membersInToday}</strong><span>members present</span></div><i/><div><strong>{props.staffAvatars.length}</strong><span>staff on site</span></div></div>
                    <p>The printable fire register uses these live attendance records.</p>
                    <div>{canUseRegister && <Link href="/register">Open morning register</Link>}<Link href="/fire-register?print=1">Print fire register</Link></div>
                </article>
            </section>

            <section className="overview-support-grid">
                <article className="overview-panel">
                    <PanelTitle icon="users" title="Member support" href="/members" action="Open members"/>
                    <div className="overview-service-list">
                        {canViewMemberDetails && <Link href="/reviews"><AppIcon name="file"/><div><b>Support-plan reviews</b><span>{reviewNotices.length ? `${reviewNotices.length} reviews due` : 'No reviews currently due'}</span></div><em>Review</em></Link>}
                        {canUseRegister && <Link href="/end-of-day"><AppIcon name="activity"/><div><b>Session outcomes</b><span>Record progress and achievements</span></div><em>Record</em></Link>}
                        {capabilities.includes('view_documents') && <Link href="/documents"><AppIcon name="folder"/><div><b>Member documents</b><span>Support plans and personal records</span></div><em>Open</em></Link>}
                    </div>
                </article>

                <article className="overview-panel overview-welfare-panel">
                    <PanelTitle icon="paw" title="Animal welfare" href="/animals" action="Open animals"/>
                    <div className="overview-welfare-progress"><div><b>Daily checks</b><strong>{props.stats.animalsChecked} of {props.stats.animalsTotal} recorded</strong></div><div><i style={{ width: `${props.stats.animalsTotal ? Math.round((props.stats.animalsChecked / props.stats.animalsTotal) * 100) : 0}%` }}/></div></div>
                    <div className="overview-welfare-stats"><div><span>Checks complete</span><b>{props.stats.animalsChecked}</b></div><div><span>Outstanding</span><b>{props.stats.animalsNeedingChecks}</b></div><div><span>Welfare alerts</span><b>{props.welfareAlerts.length}</b></div></div>
                    <footer><span className={props.stats.animalsNeedingChecks ? '' : 'is-clear'}>{props.stats.animalsNeedingChecks ? `${props.stats.animalsNeedingChecks} checks outstanding` : 'All checks complete'}</span><Link href="/animals"><AppIcon name="plus"/> Record welfare check</Link></footer>
                </article>
            </section>

            <h2 className="overview-section-heading">Quick actions</h2>
            <section className="overview-quick-actions">{availableQuickActions.map((action) => <Link href={action.href} key={action.href}><AppIcon name={action.icon}/><b>{action.label}</b><AppIcon name="arrow"/></Link>)}</section>

            <section className="overview-bottom-grid">
                <article className="overview-panel">
                    <PanelTitle icon="today" title="Today’s workflow" href="/today" action="Open full list"/>
                    <div className="overview-workflow">{props.checklist.map((item) => <div key={item.key} className={item.done ? 'is-done' : ''}><i>{item.done ? '✓' : ''}</i><span><b>{item.label}</b><small>{item.done ? 'Completed today' : 'Still needs attention'}</small></span><em>{item.done ? 'Done' : 'Open'}</em></div>)}</div>
                </article>
                <article className="overview-panel">
                    <PanelTitle icon="megaphone" title="Latest updates" href="/announcements" action="All updates"/>
                    <div className="overview-updates">{props.announcements.length ? props.announcements.slice(0, 4).map((item) => <Link href="/announcements" key={item.id}><i className={item.read ? '' : 'is-new'}/><span><b>{item.title}</b><small>{item.author} · {new Date(item.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</small></span></Link>) : <p>No announcements yet.</p>}</div>
                    {props.birthdays.length > 0 && <div className="overview-birthdays"><b>Coming up</b>{props.birthdays.map((birthday) => canViewMemberDetails ? <Link href={`/members/${birthday.id}`} key={birthday.id}>{birthday.name} · {birthday.is_today ? 'today' : birthday.date}</Link> : <span key={birthday.id}>{birthday.name} · {birthday.is_today ? 'today' : birthday.date}</span>)}</div>}
                </article>
            </section>

            <footer className="overview-footer"><span>Live operational data · confidential</span><span>Areterra Hub / {auth.user?.role?.replace('_', ' ')} workspace</span></footer>
        </div>
    </AppShell>;
}
