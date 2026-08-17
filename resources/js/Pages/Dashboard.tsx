import { Head, Link, router, usePage } from '@inertiajs/react';
import { CSSProperties, useEffect, useMemo, useState } from 'react';
import AppShell from '../components/AppShell';
import EmptyState from '../components/EmptyState';
import StatusPill from '../components/StatusPill';
import { ChecklistItem, SharedProps } from '../types';

interface Props {
    orgIsEmpty: boolean;
    birthdays: { id: number; name: string; date: string; is_today: boolean }[];
    stats: { membersInToday: number; membersScheduled: number; animalsNeedingChecks: number; attendanceTrend: number[] };
    welfareAlerts: { id: number; name: string; species: string; welfare_status: string }[];
    checklist: ChecklistItem[];
    banner: string | null;
    staffAvatars: string[];
    myShift: { clock_in: string } | null;
    leaveBalance: { entitlement: number; taken: number; remaining: number };
    announcements: { id: number; title: string; author: string; created_at: string; read: boolean }[];
    notifications: { id: string; title: string; body: string; url: string; read: boolean; created_at: string }[];
}

function greeting() {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
}

function CountUp({ value }: { value: number }) {
    const [display, setDisplay] = useState(0);

    useEffect(() => {
        if (value === 0) {
            setDisplay(0);
            return;
        }

        let frame = 0;
        const frames = 18;
        const timer = window.setInterval(() => {
            frame += 1;
            setDisplay(Math.round((value * frame) / frames));
            if (frame >= frames) window.clearInterval(timer);
        }, 32);

        return () => window.clearInterval(timer);
    }, [value]);

    return <>{display}</>;
}

const quickActions = [
    { href: '/register', label: 'Morning register', description: 'Record arrivals and attendance', icon: '📋' },
    { href: '/monitoring', label: 'Daily monitoring', description: 'Add member observations', icon: '📊' },
    { href: '/animals', label: 'Animal welfare', description: 'Complete welfare checks', icon: '🦜' },
    { href: '/end-of-day', label: 'End of day', description: 'Close today’s records', icon: '🌙' },
];

const avatarColours = ['dashboard-avatar-blue', 'dashboard-avatar-green', 'dashboard-avatar-purple', 'dashboard-avatar-orange'];

export default function Dashboard({
    orgIsEmpty,
    birthdays,
    stats,
    welfareAlerts,
    checklist,
    banner,
    staffAvatars,
    myShift,
    leaveBalance,
    announcements,
    notifications,
}: Props) {
    const { auth } = usePage<SharedProps>().props;
    const firstName = auth.user?.name?.split(' ')[0] ?? 'there';
    const completed = checklist.filter((item) => item.done).length;
    const completion = checklist.length ? Math.round((completed / checklist.length) * 100) : 0;
    const weeklyTotal = stats.attendanceTrend.reduce((sum, value) => sum + value, 0);
    const attendanceRate = stats.membersScheduled > 0
        ? Math.round((stats.membersInToday / stats.membersScheduled) * 100)
        : 0;

    const chartPoints = useMemo(() => {
        const values = stats.attendanceTrend.length ? stats.attendanceTrend : [0, 0, 0, 0, 0];
        const max = Math.max(...values, 1);
        return values.map((value, index) => {
            const x = values.length === 1 ? 50 : (index / (values.length - 1)) * 100;
            const y = 84 - (value / max) * 64;
            return `${x},${y}`;
        }).join(' ');
    }, [stats.attendanceTrend]);

    if (orgIsEmpty) {
        return (
            <AppShell title="Dashboard">
                <Head title="Dashboard" />
                <section className="dashboard-empty-state">
                    <div className="dashboard-empty-illustration">🦜🐰🐹</div>
                    <p className="dashboard-kicker">Your new workspace</p>
                    <h2>Welcome to Areterra Hub, {firstName}.</h2>
                    <p>Add your first members and animals to bring attendance, welfare, records and daily operations to life.</p>
                    <div className="dashboard-empty-actions">
                        <Link href="/members">+ Add a member</Link>
                        <Link href="/animals">+ Add an animal</Link>
                    </div>
                </section>
            </AppShell>
        );
    }

    return (
        <AppShell title="Dashboard">
            <Head title="Dashboard" />

            <div className="dashboard-page">
                {banner && <div className="dashboard-banner">📣 {banner}</div>}

                <section className="dashboard-hero">
                    <div className="dashboard-hero-copy">
                        <p className="dashboard-kicker">Live service overview</p>
                        <h2>{greeting()}, {firstName}.</h2>
                        <p className="dashboard-hero-summary">
                            {stats.membersInToday} members are in today, {completed} of {checklist.length} daily actions are complete,
                            and {welfareAlerts.length === 0 ? 'there are no current welfare alerts.' : `${welfareAlerts.length} welfare alert${welfareAlerts.length === 1 ? '' : 's'} need attention.`}
                        </p>
                        <div className="dashboard-hero-actions">
                            <Link href="/today" className="dashboard-primary-button">Open today’s overview</Link>
                            <Link href="/register" className="dashboard-secondary-button">Take register</Link>
                        </div>
                    </div>

                    <div className="dashboard-live-orbit" aria-label="Today’s service completion">
                        <div className="dashboard-orbit-ring" style={{ '--dashboard-progress': `${completion * 3.6}deg` } as CSSProperties}>
                            <div>
                                <strong>{completion}%</strong>
                                <span>daily actions complete</span>
                            </div>
                        </div>
                        <div className="dashboard-live-label"><i /> Live now</div>
                    </div>
                </section>

                <section className="dashboard-stat-grid">
                    <article className="dashboard-stat-card dashboard-stat-primary">
                        <div className="dashboard-stat-icon">👥</div>
                        <div><span>Members in today</span><strong><CountUp value={stats.membersInToday} /><small>/{stats.membersScheduled}</small></strong></div>
                        <div className="dashboard-stat-meta">{attendanceRate}% of today’s schedule</div>
                    </article>
                    <article className="dashboard-stat-card">
                        <div className="dashboard-stat-icon">🦜</div>
                        <div><span>Welfare checks</span><strong><CountUp value={stats.animalsNeedingChecks} /></strong></div>
                        <div className="dashboard-stat-meta">{stats.animalsNeedingChecks ? 'Still awaiting completion' : 'All currently complete'}</div>
                    </article>
                    <article className="dashboard-stat-card">
                        <div className="dashboard-stat-icon">✅</div>
                        <div><span>Daily checklist</span><strong><CountUp value={completed} /><small>/{checklist.length}</small></strong></div>
                        <div className="dashboard-stat-meta">{checklist.length - completed} action{checklist.length - completed === 1 ? '' : 's'} remaining</div>
                    </article>
                    <article className="dashboard-stat-card">
                        <div className="dashboard-stat-icon">📈</div>
                        <div><span>Weekly attendance</span><strong><CountUp value={weeklyTotal} /></strong></div>
                        <div className="dashboard-stat-meta">Across the current week</div>
                    </article>
                </section>

                <section className="dashboard-main-grid">
                    <div className="dashboard-main-column">
                        <article className="dashboard-panel dashboard-attendance-panel">
                            <div className="dashboard-panel-heading">
                                <div><p className="dashboard-kicker">Attendance intelligence</p><h3>This week at Areterra</h3></div>
                                <Link href="/reports">View reports →</Link>
                            </div>
                            <div className="dashboard-chart-layout">
                                <div className="dashboard-chart-number"><strong>{weeklyTotal}</strong><span>total attendances</span><em>Live data from the register</em></div>
                                <div className="dashboard-line-chart">
                                    <div className="dashboard-chart-grid" />
                                    <svg viewBox="0 0 100 100" preserveAspectRatio="none" role="img" aria-label="Weekly attendance trend">
                                        <defs>
                                            <linearGradient id="dashboardArea" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stopColor="var(--color-brand)" stopOpacity="0.26" />
                                                <stop offset="100%" stopColor="var(--color-brand)" stopOpacity="0" />
                                            </linearGradient>
                                        </defs>
                                        <polygon points={`0,100 ${chartPoints} 100,100`} fill="url(#dashboardArea)" />
                                        <polyline points={chartPoints} fill="none" stroke="var(--color-brand)" strokeWidth="2.5" vectorEffect="non-scaling-stroke" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                    <div className="dashboard-chart-days"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div>
                                </div>
                            </div>
                        </article>

                        <article className="dashboard-panel">
                            <div className="dashboard-panel-heading">
                                <div><p className="dashboard-kicker">Today’s workflow</p><h3>What needs doing</h3></div>
                                <Link href="/today">Open full list →</Link>
                            </div>
                            <div className="dashboard-checklist">
                                {checklist.map((item, index) => (
                                    <div key={item.key} className={`dashboard-check-item ${item.done ? 'is-complete' : ''}`}>
                                        <div className="dashboard-check-marker">{item.done ? '✓' : index + 1}</div>
                                        <div><strong>{item.label}</strong><span>{item.done ? 'Completed today' : 'Still needs attention'}</span></div>
                                        <div className="dashboard-check-status">{item.done ? 'Done' : 'Open'}</div>
                                    </div>
                                ))}
                            </div>
                        </article>

                        {welfareAlerts.length > 0 && (
                            <article className="dashboard-panel dashboard-alert-panel">
                                <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">Welfare</p><h3>Alerts requiring attention</h3></div><Link href="/animals">All animals →</Link></div>
                                <div className="dashboard-alert-list">
                                    {welfareAlerts.map((alert) => (
                                        <Link href={`/animals/${alert.id}`} key={alert.id} className="dashboard-alert-row">
                                            <div className="dashboard-alert-avatar">{alert.name.charAt(0)}</div>
                                            <div><strong>{alert.name}</strong><span>{alert.species}</span></div>
                                            <StatusPill status={alert.welfare_status} />
                                        </Link>
                                    ))}
                                </div>
                            </article>
                        )}
                    </div>

                    <aside className="dashboard-side-column">
                        <article className="dashboard-panel">
                            <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">Quick launch</p><h3>Common actions</h3></div></div>
                            <div className="dashboard-action-grid">
                                {quickActions.map((action) => (
                                    <Link key={action.href} href={action.href} className="dashboard-action-card">
                                        <span>{action.icon}</span><strong>{action.label}</strong><small>{action.description}</small>
                                    </Link>
                                ))}
                            </div>
                        </article>

                        <article className="dashboard-panel dashboard-team-panel">
                            <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">Team status</p><h3>Today’s staff</h3></div><Link href="/directory">Directory →</Link></div>
                            {staffAvatars.length > 0 ? (
                                <div className="dashboard-team-list">
                                    {staffAvatars.map((name, index) => (
                                        <div key={`${name}-${index}`} className="dashboard-team-row">
                                            <div className={`dashboard-team-avatar ${avatarColours[index % avatarColours.length]}`}>{name.charAt(0)}</div>
                                            <div><strong>{name}</strong><span>On today’s team</span></div>
                                            <i />
                                        </div>
                                    ))}
                                </div>
                            ) : <EmptyState icon="👥" text="No staff are scheduled yet." />}
                        </article>

                        <article className="dashboard-panel dashboard-personal-panel">
                            <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">My workspace</p><h3>Shift and leave</h3></div></div>
                            <div className="dashboard-personal-row">
                                <div className="dashboard-personal-icon">⏱️</div>
                                <div><strong>{myShift ? `Clocked in at ${myShift.clock_in}` : 'Not clocked in'}</strong><span>Time clock status</span></div>
                                {myShift ? <Link href="/timeclock">Open</Link> : <button onClick={() => router.post('/timeclock/in')}>Clock in</button>}
                            </div>
                            <div className="dashboard-personal-row">
                                <div className="dashboard-personal-icon">🌴</div>
                                <div><strong>{leaveBalance.remaining} days remaining</strong><span>{leaveBalance.taken} of {leaveBalance.entitlement} used</span></div>
                                <Link href="/leave">Manage</Link>
                            </div>
                        </article>

                        {birthdays.length > 0 && (
                            <article className="dashboard-panel">
                                <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">Coming up</p><h3>🎂 Birthdays</h3></div></div>
                                <div className="dashboard-feed">
                                    {birthdays.map((b) => (
                                        <Link href={`/members/${b.id}`} key={b.id} className="dashboard-feed-item">
                                            <strong>{b.is_today ? `🎉 ${b.name} — today!` : `${b.name} — ${b.date}`}</strong>
                                        </Link>
                                    ))}
                                </div>
                            </article>
                        )}

                        <article className="dashboard-panel">
                            <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">Latest updates</p><h3>Announcements</h3></div><Link href="/announcements">All →</Link></div>
                            {announcements.length === 0 ? <EmptyState icon="📢" text="No announcements yet." /> : (
                                <div className="dashboard-feed">
                                    {announcements.slice(0, 4).map((announcement) => (
                                        <Link href="/announcements" key={announcement.id} className="dashboard-feed-item">
                                            <i className={announcement.read ? '' : 'is-unread'} />
                                            <div><strong>{announcement.title}</strong><span>{announcement.author} · {new Date(announcement.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}</span></div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </article>

                        {notifications.length > 0 && (
                            <article className="dashboard-panel">
                                <div className="dashboard-panel-heading"><div><p className="dashboard-kicker">Inbox</p><h3>Recent notifications</h3></div><Link href="/notifications">All →</Link></div>
                                <div className="dashboard-feed">
                                    {notifications.slice(0, 3).map((notification) => (
                                        <Link
                                            href={notification.url}
                                            key={notification.id}
                                            onClick={() => !notification.read && router.post(`/notifications/${notification.id}/read`, {}, { preserveScroll: true, preserveState: true })}
                                            className="dashboard-feed-item"
                                        >
                                            <i className={notification.read ? '' : 'is-unread'} />
                                            <div><strong>{notification.title}</strong><span>{notification.body}</span></div>
                                        </Link>
                                    ))}
                                </div>
                            </article>
                        )}
                    </aside>
                </section>
            </div>
        </AppShell>
    );
}
