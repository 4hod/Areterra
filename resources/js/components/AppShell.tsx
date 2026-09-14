import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import { SharedProps } from '../types';
import SearchOverlay from './SearchOverlay';
import DialogHost from './DialogHost';
import AccountMenu from './AccountMenu';
import NotificationBell from './NotificationBell';

export interface NavItem {
    href: string;
    label: string;
    icon: string;
    cap?: string;
    isNew?: boolean;
    matches?: string[];
}

export const NAV_SECTIONS: { title: string | null; color: string; items: NavItem[] }[] = [
    {
        title: null,
        color: 'brand',
        items: [
            { href: '/', label: 'Dashboard', icon: '🏠' },
            { href: '/today', label: 'Today', icon: '✅' },
        ],
    },
    {
        title: 'Workspaces',
        color: 'cat-people',
        items: [
            { href: '/people', label: 'People', icon: '👥', matches: ['/members', '/monitoring', '/reviews', '/referrals', '/email', '/sar-requests'] },
            { href: '/animal-care', label: 'Animal Care', icon: '🐾', matches: ['/animals'] },
            { href: '/operations', label: 'Operations', icon: '🧭', matches: ['/register', '/transport', '/end-of-day', '/activities', '/weekly-planner', '/calendar', '/vehicles', '/maintenance', '/projects', '/insurance'] },
            { href: '/team', label: 'Team', icon: '🤝', matches: ['/directory', '/announcements', '/recognition', '/leave', '/timeclock', '/supervisions', '/payroll', '/orders'] },
            { href: '/business', label: 'Business', icon: '💼', matches: ['/finance', '/funding', '/invoices', '/reports'] },
            { href: '/governance', label: 'Governance', icon: '⚖️', matches: ['/policies', '/documents', '/risk-assessments', '/compliance', '/incidents', '/safeguarding', '/forms', '/audit', '/settings', '/import'] },
        ],
    },
    {
        title: 'Utilities',
        color: 'cat-admin',
        items: [
            { href: '/search-hub', label: 'Search', icon: '🔍' },
            { href: '/tasks', label: 'Tasks', icon: '☑️' },
            { href: '/notifications', label: 'Notifications', icon: '🔔' },
            { href: '/account', label: 'My account', icon: '👤' },
        ],
    },
];

// Keep the established mobile navigation order.
const MOBILE_NAV: NavItem[] = [
    { href: '/today', label: 'Today', icon: '✅' },
    { href: '/people', label: 'People', icon: '👥', matches: ['/members', '/monitoring', '/reviews', '/referrals'] },
    { href: '/animal-care', label: 'Animals', icon: '🐾', matches: ['/animals'] },
    { href: '/operations', label: 'Operations', icon: '🧭', matches: ['/register', '/transport', '/end-of-day', '/activities', '/weekly-planner', '/calendar', '/vehicles', '/maintenance', '/projects'] },
    { href: '/more', label: 'More', icon: '⋯' },
];

function isActive(href: string, url: string) {
    return href === '/' ? url === '/' : url.startsWith(href);
}

function itemIsActive(item: NavItem, url: string) {
    return isActive(item.href, url) || item.matches?.some((href) => isActive(href, url)) === true;
}

export function allowed(item: NavItem, caps: string[]) {
    return !item.cap || caps.includes(item.cap);
}

export default function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, flash, branding, unreadNotifications } = usePage<SharedProps>().props;
    const url = usePage().url;
    const caps = auth.user?.capabilities ?? [];
    const [toast, setToast] = useState<{ message: string; type: 'success' | 'error' | 'warning' } | null>(null);
    const [drawer, setDrawer] = useState(false);
    const [searching, setSearching] = useState(false);
    const sidebarRef = useRef<HTMLElement | null>(null);
    const [pushPrompt, setPushPrompt] = useState(false);
    const [openSections, setOpenSections] = useState<Record<string, boolean>>(() => {
        let stored: Record<string, boolean> = {};
        try {
            stored = JSON.parse(localStorage.getItem('ah-nav-open') ?? '{}');
        } catch {
            stored = {};
        }
        if (stored.Workspaces === undefined) stored.Workspaces = true;
        // Always auto-open whichever section contains the current page, regardless
        // of stored state, so navigating somewhere never hides where you just went.
        for (const section of NAV_SECTIONS) {
            if (section.title && section.items.some((item) => itemIsActive(item, url))) {
                stored[section.title] = true;
            }
        }
        return stored;
    });


    // Keep the desktop navigation's own scroll position between Inertia page visits.
    // The content page itself is still allowed to return to the top normally.
    useEffect(() => {
        const sidebar = sidebarRef.current;
        if (!sidebar) return;

        const saved = Number(sessionStorage.getItem('ah-sidebar-scroll') ?? '0');
        if (Number.isFinite(saved)) {
            sidebar.scrollTop = saved;
        }

        const rememberPosition = () => {
            sessionStorage.setItem('ah-sidebar-scroll', String(sidebar.scrollTop));
        };

        sidebar.addEventListener('scroll', rememberPosition, { passive: true });
        return () => sidebar.removeEventListener('scroll', rememberPosition);
    }, []);

    function rememberSidebarPosition() {
        if (sidebarRef.current) {
            sessionStorage.setItem('ah-sidebar-scroll', String(sidebarRef.current.scrollTop));
        }
    }

    function toggleSection(title: string) {
        setOpenSections((prev) => {
            const next = { ...prev, [title]: !prev[title] };
            localStorage.setItem('ah-nav-open', JSON.stringify(next));
            return next;
        });
    }

    useEffect(() => {
        if (flash.success) {
            setToast({ message: flash.success, type: 'success' });
        } else if (flash.error) {
            setToast({ message: flash.error, type: 'error' });
        } else {
            setToast(null);
            return;
        }
        const t = setTimeout(() => setToast(null), 3500);
        return () => clearTimeout(t);
    }, [flash]);

    // Cmd+K / Ctrl+K opens search from anywhere.
    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearching(true);
            }
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    // Push permission prompt: 3s after login, dismissible, once per session (SPEC.md §22).
    useEffect(() => {
        if (
            'Notification' in window &&
            Notification.permission === 'default' &&
            !sessionStorage.getItem('ah-push-prompted')
        ) {
            const t = setTimeout(() => setPushPrompt(true), 3000);
            return () => clearTimeout(t);
        }
    }, []);

    function dismissPushPrompt() {
        sessionStorage.setItem('ah-push-prompted', '1');
        setPushPrompt(false);
    }

    return (
        <div className="min-h-screen md:flex hub-app-shell">
            {/* Desktop sidebar */}
            <aside
                ref={sidebarRef}
                className="hub-sidebar hidden md:flex md:flex-col w-60 shrink-0 text-white h-screen max-h-screen sticky top-0 overflow-y-auto overscroll-contain"
                style={{ background: 'linear-gradient(180deg, var(--color-brand-dark) 0%, var(--color-ink) 100%)' }}
            >
                <div className="px-5 py-5">
                    {branding.logoUrl ? (
                        <img src={branding.logoUrl} alt={branding.orgName} className="h-9 max-w-[160px] object-contain object-left mb-1" />
                    ) : (
                        <div className="text-xl font-extrabold">{branding.orgName}</div>
                    )}
                    <div className="text-xs text-white/60 mt-1">Animals. People. Purpose.</div>
                </div>
                <nav className="flex-1 px-3 pb-4 space-y-1">
                    {NAV_SECTIONS.map((section, i) => {
                        const items = section.items.filter((item) => allowed(item, caps));
                        if (items.length === 0) return null;

                        // The untitled top section (Dashboard/Today/etc) has no header
                        // and is always expanded — everything else collapses.
                        if (!section.title) {
                            return (
                                <div key={i} className="space-y-0.5 pb-3">
                                    {items.map((item) => (
                                        <Link
                                            onClick={rememberSidebarPosition}
                                            key={item.href}
                                            href={item.href}
                                            className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors ${
                                                itemIsActive(item, url)
                                                    ? 'bg-brand text-white shadow-sm'
                                                    : 'text-white/70 hover:bg-white/5 hover:text-white'
                                            }`}
                                        >
                                            <span className="text-base shrink-0 w-5 text-center" aria-hidden>{item.icon}</span>
                                            <span className="flex-1">{item.label}</span>
                                            {item.isNew && (
                                                <span className="rounded bg-accent text-brand-dark text-[9px] font-extrabold px-1.5 py-0.5 tracking-wide">
                                                    NEW
                                                </span>
                                            )}
                                        </Link>
                                    ))}
                                </div>
                            );
                        }

                        const isOpen = openSections[section.title] ?? false;

                        return (
                            <div key={i} className="pt-2 mt-1 border-t border-white/10 first:border-t-0 first:mt-0 first:pt-0">
                                <button
                                    onClick={() => toggleSection(section.title!)}
                                    className="w-full flex items-center justify-between px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-white/55 hover:text-white"
                                >
                                    <span>{section.title}</span>
                                    <span className={`transition-transform ${isOpen ? 'rotate-90' : ''}`} aria-hidden>
                                        ›
                                    </span>
                                </button>
                                {isOpen && (
                                    <div className="space-y-0.5 pb-2">
                                        {items.map((item) => (
                                            <Link
                                                onClick={rememberSidebarPosition}
                                                key={item.href}
                                                href={item.href}
                                                className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition-colors ${
                                                    itemIsActive(item, url)
                                                        ? 'bg-brand text-white shadow-sm'
                                                        : 'text-white/70 hover:bg-white/5 hover:text-white'
                                                }`}
                                            >
                                                <span className="text-base shrink-0 w-5 text-center" aria-hidden>{item.icon}</span>
                                                <span className="flex-1">{item.label}</span>
                                                {item.isNew && (
                                                    <span className="rounded bg-accent text-brand-dark text-[9px] font-extrabold px-1.5 py-0.5 tracking-wide">
                                                        NEW
                                                    </span>
                                                )}
                                            </Link>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </nav>
                <div className="p-4 border-t border-white/10 text-sm">
                    <div className="font-semibold truncate">{auth.user?.name}</div>
                    <div className="text-white/50 capitalize text-xs">{auth.user?.role?.replace('_', ' ')}</div>
                    <div className="mt-2 flex gap-3 text-xs">
                        <Link href="/account" className="text-white/70 hover:text-white">
                            My account
                        </Link>
                        <button onClick={() => router.post('/logout')} className="text-white/70 hover:text-white">
                            Log out
                        </button>
                    </div>
                </div>
            </aside>

            <div className="hub-main-column flex-1 min-w-0">
                <header className="hub-topbar sticky top-0 z-40 px-4 py-3.5 flex items-center gap-3 md:px-8">
                    <button
                        onClick={() => setDrawer(true)}
                        aria-label="Open menu"
                        className="md:hidden h-11 w-11 -ml-2 rounded-full hover:bg-black/5 text-xl"
                    >
                        ☰
                    </button>
                    <div className="hub-topbar-title text-xl font-semibold text-brand-dark truncate">{title}</div>

                    <div className="flex items-center gap-1 md:gap-2 ml-auto">
                        <button
                            onClick={() => setSearching(true)}
                            aria-label="Search"
                            title="Search (⌘K)"
                            className="h-10 w-10 rounded-full hover:bg-black/5 flex items-center justify-center text-lg"
                        >
                            🔍
                        </button>
                        <NotificationBell unreadCount={unreadNotifications} />
                        <AccountMenu
                            name={auth.user?.name ?? ''}
                            role={auth.user?.role ?? ''}
                            canManageSettings={caps.includes('manage_settings')}
                        />
                        <button
                            onClick={() => router.post('/logout')}
                            aria-label="Log out"
                            className="md:hidden h-11 w-11 rounded-full hover:bg-black/5"
                        >
                            ⎋
                        </button>
                    </div>
                </header>

                <main className="hub-page-content p-4 md:p-8 pb-28 md:pb-20 max-w-[1600px]">{children}</main>
            </div>

            <a
                href="https://rockitfox.co.uk"
                target="_blank"
                rel="noreferrer"
                className="fixed right-4 bottom-20 md:bottom-4 z-30 rounded-full border border-slate-200/80 bg-slate-800/80 px-4 py-2 text-[11px] font-medium text-white/70 shadow-lg backdrop-blur transition hover:bg-slate-800 hover:text-white"
                aria-label="Website designed and built by RockitFox"
            >
                Site designed &amp; built by <span className="font-extrabold text-orange-300">🚀 RockitFox</span>
            </a>

            {/* Mobile bottom nav */}
            <nav className="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 flex pb-[env(safe-area-inset-bottom)]">
                {MOBILE_NAV.filter((item) => allowed(item, caps)).map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`flex-1 flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium min-h-11 ${
                            itemIsActive(item, url) ? 'text-brand' : 'text-slate-500'
                        }`}
                    >
                        <span className="text-xl" aria-hidden>
                            {item.icon}
                        </span>
                        {item.label}
                    </Link>
                ))}
            </nav>

            {/* Mobile drawer */}
            {drawer && (
                <div className="md:hidden fixed inset-0 z-50 bg-black/40" onClick={() => setDrawer(false)}>
                    <div
                        className="absolute inset-y-0 left-0 w-72 bg-brand-dark text-white overflow-y-auto p-4"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {branding.logoUrl ? (
                            <img src={branding.logoUrl} alt={branding.orgName} className="h-8 max-w-[160px] object-contain object-left mb-3" />
                        ) : (
                            <div className="text-lg font-extrabold mb-3">{branding.orgName}</div>
                        )}
                        {NAV_SECTIONS.map((section, i) => {
                            const items = section.items.filter((item) => allowed(item, caps));
                            if (items.length === 0) return null;

                            if (!section.title) {
                                return (
                                    <div key={i} className="mb-3">
                                        {items.map((item) => (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                onClick={() => setDrawer(false)}
                                                className={`flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium ${
                                                    itemIsActive(item, url) ? 'bg-brand text-white' : 'text-white/75'
                                                }`}
                                            >
                                                <span className="text-base shrink-0 w-5 text-center" aria-hidden>{item.icon}</span>
                                                {item.label}
                                            </Link>
                                        ))}
                                    </div>
                                );
                            }

                            const isOpen = openSections[section.title] ?? false;

                            return (
                                <div key={i} className="mb-1">
                                    <button
                                        onClick={() => toggleSection(section.title!)}
                                        className="w-full flex items-center justify-between px-2 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white/40"
                                    >
                                        <span>{section.title}</span>
                                        <span className={`transition-transform ${isOpen ? 'rotate-90' : ''}`} aria-hidden>›</span>
                                    </button>
                                    {isOpen && (
                                        <div className="mb-2">
                                            {items.map((item) => (
                                                <Link
                                                    key={item.href}
                                                    href={item.href}
                                                    onClick={() => setDrawer(false)}
                                                    className={`flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium ${
                                                        itemIsActive(item, url) ? 'bg-brand text-white' : 'text-white/75'
                                                    }`}
                                                >
                                                    <span className="text-base shrink-0 w-5 text-center" aria-hidden>{item.icon}</span>
                                                    {item.label}
                                                </Link>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Push permission prompt (3s after login, once per session) */}
            {pushPrompt && (
                <div className="fixed bottom-20 md:bottom-6 right-4 z-50 max-w-xs bg-white rounded-card shadow-xl border border-slate-200 p-4">
                    <div className="font-bold text-brand-dark text-sm">🔔 Stay in the loop</div>
                    <p className="text-xs text-slate-500 mt-1">
                        Turn on notifications for announcements, welfare alerts and reminders.
                    </p>
                    <div className="flex gap-2 mt-3">
                        <Link
                            href="/notifications"
                            onClick={dismissPushPrompt}
                            className="rounded-full bg-brand text-white text-xs font-bold px-3 py-2"
                        >
                            Enable
                        </Link>
                        <button onClick={dismissPushPrompt} className="rounded-full bg-slate-100 text-slate-500 text-xs font-bold px-3 py-2">
                            Not now
                        </button>
                    </div>
                </div>
            )}

            {toast && (
                <div
                    className={`fixed bottom-20 md:bottom-6 left-1/2 -translate-x-1/2 z-50 text-white text-sm font-medium px-4 py-2.5 rounded-full shadow-lg flex items-center gap-2 ${
                        toast.type === 'success' ? 'bg-status-green' : toast.type === 'error' ? 'bg-status-red' : 'bg-status-amber'
                    }`}
                >
                    <span aria-hidden>{toast.type === 'success' ? '✓' : toast.type === 'error' ? '✕' : '⚠'}</span>
                    {toast.message}
                </div>
            )}

            {searching && <SearchOverlay onClose={() => setSearching(false)} />}
            <DialogHost />
        </div>
    );
}
