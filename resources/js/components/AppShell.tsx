import { Link, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import { SharedProps } from '../types';
import AccountMenu from './AccountMenu';
import AppIcon from './AppIcon';
import DialogHost from './DialogHost';
import NotificationBell from './NotificationBell';
import SearchOverlay from './SearchOverlay';

interface NavItem { href: string; label: string; icon: string; cap?: string }
interface NavSection { title: string; items: NavItem[] }

export const NAV_SECTIONS: NavSection[] = [
    { title: 'Workspace', items: [
        { href: '/', label: 'Management overview', icon: 'grid' },
        { href: '/today', label: 'Daily work', icon: 'today' },
        { href: '/tasks', label: 'Tasks', icon: 'tasks' },
        { href: '/members', label: 'Members', icon: 'users', cap: 'view_members' },
        { href: '/animals', label: 'Animals & welfare', icon: 'paw', cap: 'view_animals' },
        { href: '/calendar', label: 'Activities & outcomes', icon: 'activity' },
    ] },
    { title: 'Daily operations', items: [
        { href: '/transport', label: 'Transport', icon: 'truck', cap: 'log_sessions' },
        { href: '/register', label: 'Morning register & fire list', icon: 'register', cap: 'log_sessions' },
        { href: '/end-of-day', label: 'End of day', icon: 'moon', cap: 'log_sessions' },
        { href: '/monitoring', label: 'Daily monitoring', icon: 'monitor', cap: 'log_welfare' },
    ] },
    { title: 'Records', items: [
        { href: '/forms', label: 'Forms', icon: 'file' },
        { href: '/reports', label: 'Impact reporting', icon: 'chart', cap: 'view_reports' },
        { href: '/reviews', label: 'Member reviews', icon: 'activity', cap: 'view_members' },
        { href: '/incidents', label: 'Incidents', icon: 'alert', cap: 'report_incidents' },
        { href: '/compliance', label: 'Compliance', icon: 'shield', cap: 'view_all_compliance' },
        { href: '/documents', label: 'Documents', icon: 'folder' },
        { href: '/risk-assessments', label: 'Risk assessments', icon: 'shield' },
        { href: '/sar-requests', label: 'SAR requests', icon: 'lock', cap: 'edit_members' },
    ] },
    { title: 'Team', items: [
        { href: '/leave', label: 'Leave', icon: 'leave', cap: 'request_leave' },
        { href: '/directory', label: 'Staff directory', icon: 'directory' },
        { href: '/announcements', label: 'Announcements', icon: 'megaphone' },
        { href: '/orders', label: 'Orders', icon: 'receipt', cap: 'request_products' },
        { href: '/supervisions', label: 'Supervisions', icon: 'users', cap: 'manage_supervisions' },
    ] },
    { title: 'Management', items: [
        { href: '/vehicles', label: 'Vehicles', icon: 'truck', cap: 'view_vehicles' },
        { href: '/maintenance', label: 'Maintenance', icon: 'wrench', cap: 'manage_operations' },
        { href: '/projects', label: 'Projects', icon: 'briefcase', cap: 'manage_operations' },
        { href: '/funding', label: 'Funding', icon: 'money', cap: 'manage_operations' },
        { href: '/insurance', label: 'Insurance', icon: 'shield', cap: 'manage_operations' },
        { href: '/automations', label: 'Automations', icon: 'activity', cap: 'manage_operations' },
        { href: '/referrals', label: 'Referrals', icon: 'mail', cap: 'create_members' },
        { href: '/finance', label: 'Finance & grants', icon: 'money', cap: 'manage_finance' },
        { href: '/invoices', label: 'Invoices', icon: 'receipt', cap: 'manage_finance' },
    ] },
    { title: 'Administration', items: [
        { href: '/audit', label: 'System audit', icon: 'shield', cap: 'view_reports' },
        { href: '/audit-log', label: 'Audit log', icon: 'file', cap: 'view_audit_log' },
        { href: '/notifications', label: 'Notifications', icon: 'bell' },
        { href: '/import', label: 'Data import', icon: 'upload', cap: 'manage_settings' },
        { href: '/settings', label: 'Settings', icon: 'settings', cap: 'manage_settings' },
        { href: '/settings/permissions', label: 'Permissions', icon: 'lock', cap: 'manage_settings' },
    ] },
];

const MOBILE_NAV: NavItem[] = [
    { href: '/today', label: 'Today', icon: 'today' },
    { href: '/register', label: 'Register', icon: 'register', cap: 'log_sessions' },
    { href: '/animals', label: 'Animals', icon: 'paw', cap: 'view_animals' },
    { href: '/members', label: 'Members', icon: 'users', cap: 'view_members' },
    { href: '/more', label: 'More', icon: 'grid' },
];

const PRIMARY_NAV: NavItem[] = [
    { href: '/', label: 'Dashboard', icon: 'grid' },
    { href: '/members', label: 'Members', icon: 'users', cap: 'view_members' },
    { href: '/animals', label: 'Animals', icon: 'paw', cap: 'view_animals' },
    { href: '/register', label: 'Attendance', icon: 'register', cap: 'log_sessions' },
    { href: '/tasks', label: 'Activities', icon: 'tasks' },
    { href: '/referrals', label: 'Referrals', icon: 'mail', cap: 'create_members' },
    { href: '/forms', label: 'Forms', icon: 'file' },
    { href: '/risk-assessments', label: 'Risk assessments', icon: 'shield' },
    { href: '/incidents', label: 'Safeguarding', icon: 'shield', cap: 'report_incidents' },
    { href: '/reports', label: 'Reports', icon: 'chart', cap: 'view_reports' },
    { href: '/more', label: 'More tools', icon: 'grid' },
];

function isActive(href: string, url: string) {
    if (href === '/') return url === '/';
    if (href === '/settings') return url === '/settings';
    return url === href || url.startsWith(`${href}/`) || url.startsWith(`${href}?`);
}
export function allowed(item: NavItem, caps: string[]) { return !item.cap || caps.includes(item.cap); }

export default function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, flash, branding, unreadNotifications, pushConfigured } = usePage<SharedProps>().props;
    const url = usePage().url;
    const caps = auth.user?.capabilities ?? [];
    const [drawer, setDrawer] = useState(false);
    const [searching, setSearching] = useState(false);
    const [favourites, setFavourites] = useState<string[]>([]);
    const [recentModules, setRecentModules] = useState<string[]>([]);
    const [pushPrompt, setPushPrompt] = useState(false);
    const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' | 'warning' } | null>(null);
    const initials = (auth.user?.name ?? 'User').split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();

    useEffect(() => {
        const next = flash.success ? { message: flash.success, type: 'success' as const }
            : flash.error ? { message: flash.error, type: 'error' as const } : null;
        setToast(next);
        if (!next) return;
        const timer = window.setTimeout(() => setToast(null), 3500);
        return () => window.clearTimeout(timer);
    }, [flash]);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setSearching(true);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (!pushConfigured || !('Notification' in window) || Notification.permission !== 'default' || sessionStorage.getItem('ah-push-prompted')) return;
        const timer = window.setTimeout(() => setPushPrompt(true), 3000);
        return () => window.clearTimeout(timer);
    }, [pushConfigured]);

    useEffect(() => {
        try {
            const storedFavourites = JSON.parse(localStorage.getItem('ah-nav-favourites') ?? '[]');
            setFavourites(Array.isArray(storedFavourites) ? storedFavourites.filter((href): href is string => typeof href === 'string') : []);
            const storedRecent = JSON.parse(localStorage.getItem('ah-nav-recent') ?? '[]');
            const recent = Array.isArray(storedRecent) ? storedRecent.filter((href): href is string => typeof href === 'string') : [];
            const next = [url.split('?')[0], ...recent.filter((href) => href !== url.split('?')[0])]
                .filter((href) => href !== '/' && href !== '/more')
                .slice(0, 4);
            setRecentModules(next);
            localStorage.setItem('ah-nav-recent', JSON.stringify(next));
        } catch {
            setFavourites([]);
            setRecentModules([]);
        }
    }, [url]);

    const availableItems = useMemo(() => NAV_SECTIONS.flatMap((section) => section.items).filter((item) => allowed(item, caps)), [caps]);

    function toggleFavourite(href: string) {
        setFavourites((current) => {
            const next = current.includes(href) ? current.filter((item) => item !== href) : [...current, href];
            localStorage.setItem('ah-nav-favourites', JSON.stringify(next));
            return next;
        });
    }

    function dismissPushPrompt() {
        sessionStorage.setItem('ah-push-prompted', '1');
        setPushPrompt(false);
    }

    const nav = (close = false, pinnable = false) => NAV_SECTIONS.map((section) => {
        const items = section.items.filter((item) => allowed(item, caps));
        if (!items.length) return null;
        return <section className="portal-nav-section" key={section.title}>
            <p>{section.title}</p>
            {items.map((item) => <div className="portal-nav-row" key={item.href}>
                <Link href={item.href} onClick={() => close && setDrawer(false)} className={isActive(item.href, url) ? 'is-active' : ''}>
                    <AppIcon name={item.icon}/><span>{item.label}</span>
                </Link>
                {pinnable && <button type="button" onClick={() => toggleFavourite(item.href)} aria-label={`${favourites.includes(item.href) ? 'Unpin' : 'Pin'} ${item.label}`} aria-pressed={favourites.includes(item.href)}>★</button>}
            </div>)}
        </section>;
    });

    const drawerLinks = (title: string, hrefs: string[]) => {
        const items = hrefs.map((href) => availableItems.find((item) => item.href === href)).filter(Boolean) as NavItem[];
        if (!items.length) return null;
        return <section className="portal-nav-section portal-drawer-shortcuts"><p>{title}</p>{items.map((item) => <Link key={item.href} href={item.href} onClick={() => setDrawer(false)} className={isActive(item.href, url) ? 'is-active' : ''}><AppIcon name={item.icon}/><span>{item.label}</span></Link>)}</section>;
    };

    const primaryNav = (close = false) => <section className="portal-nav-section portal-primary-nav">
        {PRIMARY_NAV.filter((item) => allowed(item, caps)).map((item) => <Link key={item.href} href={item.href} onClick={() => close && setDrawer(false)} className={isActive(item.href, url) ? 'is-active' : ''}>
            <AppIcon name={item.icon}/><span>{item.label}</span>
        </Link>)}
    </section>;

    const today = new Date().toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

    return <div className="portal-shell hub-app-shell">
        <aside className="portal-sidebar hidden md:flex">
            <div className="portal-brand">
                <span className="portal-brand-mark" aria-hidden="true"><i/><i/><i/></span>
                <strong>Areterra <em>Hub</em></strong>
            </div>
            <nav>{primaryNav()}</nav>
            <div className="portal-sidebar-footer">
                <div className="portal-sidebar-motto"><span>People</span><span>Animals</span><b>Brighter Futures</b></div>
                {caps.includes('manage_settings') && <Link href="/settings"><AppIcon name="settings"/><span>Settings</span></Link>}
                <Link href="/account" className="portal-user-card"><i>{initials}</i><span><b>{auth.user?.name}</b><small>{auth.user?.role?.replace('_', ' ')}</small></span></Link>
            </div>
        </aside>

        <div className="portal-main hub-main-column">
            <header className="portal-topbar hub-topbar">
                <button className="portal-menu-button md:hidden" onClick={() => setDrawer(true)} aria-label="Open navigation">☰</button>
                <Link href="/today" className="portal-mobile-brand" aria-label="Areterra Hub — Today">
                    <span className="portal-mobile-brand-mark" aria-hidden="true"><i/><i/><i/></span>
                    <strong>Areterra <em>Hub</em></strong>
                </Link>
                <button className="portal-search" onClick={() => setSearching(true)}><AppIcon name="search"/><span>Search members, animals, records…</span><kbd>Ctrl K</kbd></button>
                <button className="portal-mobile-search" onClick={() => setSearching(true)} aria-label="Search"><AppIcon name="search"/></button>
                <div className="portal-topbar-actions">
                    <span className="portal-date hidden lg:inline-flex"><AppIcon name="today"/>{today}</span>
                    <NotificationBell unreadCount={unreadNotifications}/>
                    <AccountMenu name={auth.user?.name ?? ''} role={auth.user?.role ?? ''} canManageSettings={caps.includes('manage_settings')}/>
                </div>
            </header>
            <main className="portal-content hub-page-content">{children}</main>
        </div>

        <nav className="portal-mobile-nav md:hidden">
            {MOBILE_NAV.filter((item) => allowed(item, caps)).map((item) => <Link key={item.href} href={item.href} className={isActive(item.href, url) || (item.href === '/today' && url === '/') ? 'is-active' : ''}><AppIcon name={item.icon}/><span>{item.label}</span></Link>)}
        </nav>

        {drawer && <div className="portal-drawer-backdrop md:hidden" onClick={() => setDrawer(false)}><aside className="portal-drawer" onClick={(event) => event.stopPropagation()}>
            <div className="portal-drawer-head">{branding.logoUrl ? <img src={branding.logoUrl} alt={branding.orgName}/> : <strong>{branding.orgName}</strong>}<button onClick={() => setDrawer(false)} aria-label="Close navigation">×</button></div>
            <nav>
                {drawerLinks('Favourites', favourites)}
                {drawerLinks('Recent', recentModules)}
                <section className="portal-nav-section portal-drawer-core">
                    <p>Everyday</p>
                    {MOBILE_NAV.filter((item) => item.href !== '/more' && allowed(item, caps)).map((item) => <Link key={item.href} href={item.href} onClick={() => setDrawer(false)} className={isActive(item.href, url) ? 'is-active' : ''}><AppIcon name={item.icon}/><span>{item.label}</span></Link>)}
                    <Link href="/tasks" onClick={() => setDrawer(false)}><AppIcon name="tasks"/><span>Tasks</span></Link>
                    <Link href="/more" onClick={() => setDrawer(false)}><AppIcon name="grid"/><span>All modules</span></Link>
                </section>
                <details className="portal-drawer-directory"><summary>Choose and pin modules</summary>{nav(true, true)}</details>
            </nav>
        </aside></div>}

        {pushPrompt && <div className="portal-prompt"><b>Stay in the loop</b><p>Turn on notifications for announcements, welfare alerts and reminders.</p><div><Link href="/notifications" onClick={dismissPushPrompt}>Enable</Link><button onClick={dismissPushPrompt}>Not now</button></div></div>}
        {toast && <div className={`portal-toast is-${toast.type}`}><span>{toast.type === 'success' ? '✓' : '!'}</span>{toast.message}</div>}
        {searching && <SearchOverlay onClose={() => setSearching(false)}/>}<DialogHost/>
    </div>;
}
