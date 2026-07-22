import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import { SharedProps } from '../types';
import SearchOverlay from './SearchOverlay';
import DialogHost from './DialogHost';

interface NavItem {
    href: string;
    label: string;
    icon: string;
    cap?: string;
}

export const NAV_SECTIONS: { title: string | null; color: string; items: NavItem[] }[] = [
    {
        title: null,
        color: 'brand',
        items: [
            { href: '/', label: 'Dashboard', icon: '🏠' },
            { href: '/today', label: 'Today', icon: '✅' },
            { href: '/register', label: 'Register', icon: '📋', cap: 'log_sessions' },
            { href: '/end-of-day', label: 'End of Day', icon: '🌙', cap: 'log_sessions' },
            { href: '/transport', label: 'Transport', icon: '🚐', cap: 'log_sessions' },
        ],
    },
    {
        title: 'People & animals',
        color: 'cat-people',
        items: [
            { href: '/members', label: 'Members', icon: '👥', cap: 'view_members' },
            { href: '/animals', label: 'Animals', icon: '🦜', cap: 'view_animals' },
            { href: '/monitoring', label: 'Daily Monitoring', icon: '📊', cap: 'log_welfare' },
            { href: '/reviews', label: 'Member Reviews', icon: '🔄', cap: 'view_members' },
            { href: '/email', label: 'Email Composer', icon: '✉️', cap: 'view_member_details' },
        ],
    },
    {
        title: 'Staff',
        color: 'cat-staff',
        items: [
            { href: '/announcements', label: 'Announcements', icon: '📢' },
            { href: '/recognition', label: 'Recognition', icon: '🌟' },
            { href: '/leave', label: 'Leave', icon: '🌴', cap: 'request_leave' },
            { href: '/timeclock', label: 'Time Clock', icon: '⏱️', cap: 'own_timeclock' },
            { href: '/directory', label: 'Directory', icon: '📖' },
            { href: '/orders', label: 'Orders', icon: '📦', cap: 'request_products' },
            { href: '/supervisions', label: 'Supervisions', icon: '🗣️', cap: 'manage_supervisions' },
            { href: '/payroll', label: 'Payroll', icon: '💷', cap: 'manage_payroll' },
        ],
    },
    {
        title: 'Operations',
        color: 'cat-ops',
        items: [
            { href: '/activities', label: 'Activities', icon: '📅', cap: 'log_sessions' },
            { href: '/calendar', label: 'Calendar', icon: '🗓️' },
            { href: '/vehicles', label: 'Vehicles', icon: '🚚', cap: 'view_vehicles' },
            { href: '/maintenance', label: 'Maintenance', icon: '🔧', cap: 'manage_operations' },
            { href: '/projects', label: 'Projects', icon: '🗂️', cap: 'manage_operations' },
            { href: '/funding', label: 'Funding', icon: '💰', cap: 'manage_operations' },
            { href: '/referrals', label: 'Referrals', icon: '📨', cap: 'create_members' },
            { href: '/finance', label: 'Finance & Grants', icon: '💰', cap: 'manage_finance' },
            { href: '/invoices', label: 'Invoices', icon: '🧾', cap: 'manage_finance' },
        ],
    },
    {
        title: 'Governance & safety',
        color: 'cat-governance',
        items: [
            { href: '/policies', label: 'Policies', icon: '📜' },
            { href: '/documents', label: 'Documents', icon: '📁' },
            { href: '/risk-assessments', label: 'Risk Assessments', icon: '⚖️' },
            { href: '/compliance', label: 'Compliance', icon: '📋', cap: 'view_all_compliance' },
            { href: '/incidents', label: 'Incidents', icon: '🚨', cap: 'report_incidents' },
            { href: '/safeguarding', label: 'Safeguarding', icon: '🛡️', cap: 'access_safeguarding' },
        ],
    },
    {
        title: 'Reporting & admin',
        color: 'cat-admin',
        items: [
            { href: '/audit', label: 'System Audit', icon: '🩺', cap: 'view_reports' },
            { href: '/reports', label: 'Reports', icon: '📈', cap: 'view_reports' },
            { href: '/audit-log', label: 'Audit Log', icon: '🧾', cap: 'view_audit_log' },
            { href: '/forms', label: 'Forms', icon: '📝' },
            { href: '/notifications', label: 'Notifications', icon: '🔔' },
            { href: '/import', label: 'CSV Import', icon: '📥', cap: 'manage_settings' },
            { href: '/settings', label: 'Hub Settings', icon: '⚙️', cap: 'manage_settings' },
            { href: '/account', label: 'My Account', icon: '👤' },
        ],
    },
];

// Spec order: Today, Dashboard, Members, All Animals, Announcements, More.
const MOBILE_NAV: NavItem[] = [
    { href: '/today', label: 'Today', icon: '✅' },
    { href: '/', label: 'Dashboard', icon: '🏠' },
    { href: '/members', label: 'Members', icon: '👥', cap: 'view_members' },
    { href: '/animals', label: 'Animals', icon: '🦜', cap: 'view_animals' },
    { href: '/announcements', label: 'News', icon: '📢' },
    { href: '/more', label: 'More', icon: '⋯' },
];

const BADGE_STYLES: Record<string, string> = {
    brand: 'bg-white/15',
    'cat-people': 'bg-cat-people/25',
    'cat-staff': 'bg-cat-staff/25',
    'cat-ops': 'bg-cat-ops/25',
    'cat-governance': 'bg-cat-governance/25',
    'cat-admin': 'bg-cat-admin/25',
};

function isActive(href: string, url: string) {
    return href === '/' ? url === '/' : url.startsWith(href);
}

export function allowed(item: NavItem, caps: string[]) {
    return !item.cap || caps.includes(item.cap);
}

export default function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, flash, branding } = usePage<SharedProps>().props;
    const url = usePage().url;
    const caps = auth.user?.capabilities ?? [];
    const [toast, setToast] = useState<string | null>(null);
    const [drawer, setDrawer] = useState(false);
    const [searching, setSearching] = useState(false);
    const [pushPrompt, setPushPrompt] = useState(false);
    const [openSections, setOpenSections] = useState<Record<string, boolean>>(() => {
        let stored: Record<string, boolean> = {};
        try {
            stored = JSON.parse(localStorage.getItem('ah-nav-open') ?? '{}');
        } catch {
            stored = {};
        }
        // Always auto-open whichever section contains the current page, regardless
        // of stored state, so navigating somewhere never hides where you just went.
        for (const section of NAV_SECTIONS) {
            if (section.title && section.items.some((item) => isActive(item.href, url))) {
                stored[section.title] = true;
            }
        }
        return stored;
    });

    function toggleSection(title: string) {
        setOpenSections((prev) => {
            const next = { ...prev, [title]: !prev[title] };
            localStorage.setItem('ah-nav-open', JSON.stringify(next));
            return next;
        });
    }

    useEffect(() => {
        const message = flash.success ?? flash.error ?? null;
        setToast(message);
        if (message) {
            const t = setTimeout(() => setToast(null), 3500);
            return () => clearTimeout(t);
        }
    }, [flash]);

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
        <div className="min-h-screen md:flex">
            {/* Desktop sidebar */}
            <aside
                className="hidden md:flex md:flex-col w-60 shrink-0 text-white min-h-screen sticky top-0 overflow-y-auto"
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
                                            key={item.href}
                                            href={item.href}
                                            className={`flex items-center gap-3 rounded-lg border-l-[3px] px-3 py-2 text-sm font-medium transition-colors ${
                                                isActive(item.href, url)
                                                    ? 'border-accent bg-white/10 text-white'
                                                    : 'border-transparent text-white/70 hover:bg-white/5 hover:text-white'
                                            }`}
                                        >
                                            <span
                                                className={`h-6 w-6 rounded-md flex items-center justify-center text-[13px] shrink-0 ${BADGE_STYLES[section.color]}`}
                                                aria-hidden
                                            >
                                                {item.icon}
                                            </span>
                                            {item.label}
                                        </Link>
                                    ))}
                                </div>
                            );
                        }

                        const isOpen = openSections[section.title] ?? false;

                        return (
                            <div key={i}>
                                <button
                                    onClick={() => toggleSection(section.title!)}
                                    className="w-full flex items-center justify-between px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-white/40 hover:text-white/70"
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
                                                key={item.href}
                                                href={item.href}
                                                className={`flex items-center gap-3 rounded-lg border-l-[3px] px-3 py-2 text-sm font-medium transition-colors ${
                                                    isActive(item.href, url)
                                                        ? 'border-accent bg-white/10 text-white'
                                                        : 'border-transparent text-white/70 hover:bg-white/5 hover:text-white'
                                                }`}
                                            >
                                                <span
                                                    className={`h-6 w-6 rounded-md flex items-center justify-center text-[13px] shrink-0 ${BADGE_STYLES[section.color]}`}
                                                    aria-hidden
                                                >
                                                    {item.icon}
                                                </span>
                                                {item.label}
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

            <div className="flex-1 min-w-0">
                <header className="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center gap-2 md:px-6">
                    <button
                        onClick={() => setDrawer(true)}
                        aria-label="Open menu"
                        className="md:hidden h-11 w-11 -ml-2 rounded-full hover:bg-slate-100 text-xl"
                    >
                        ☰
                    </button>
                    <h1 className="text-xl font-semibold text-brand-dark truncate flex-1 tracking-tight">{title}</h1>
                    <button
                        onClick={() => setSearching(true)}
                        aria-label="Search"
                        className="h-11 w-11 rounded-full hover:bg-slate-100 text-lg"
                    >
                        🔍
                    </button>
                    <button
                        onClick={() => router.post('/logout')}
                        aria-label="Log out"
                        className="md:hidden h-11 w-11 rounded-full hover:bg-slate-100"
                    >
                        ⎋
                    </button>
                </header>

                <main className="p-4 md:p-6 pb-24 md:pb-8 max-w-5xl">{children}</main>
            </div>

            {/* Mobile bottom nav */}
            <nav className="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-slate-200 flex pb-[env(safe-area-inset-bottom)]">
                {MOBILE_NAV.filter((item) => allowed(item, caps)).map((item) => (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`flex-1 flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium min-h-11 ${
                            isActive(item.href, url) ? 'text-brand' : 'text-slate-500'
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
                                                    isActive(item.href, url) ? 'bg-brand text-white' : 'text-white/75'
                                                }`}
                                            >
                                                <span
                                                    className={`h-6 w-6 rounded-md flex items-center justify-center text-[13px] shrink-0 ${BADGE_STYLES[section.color]}`}
                                                    aria-hidden
                                                >
                                                    {item.icon}
                                                </span>
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
                                                        isActive(item.href, url) ? 'bg-brand text-white' : 'text-white/75'
                                                    }`}
                                                >
                                                    <span
                                                        className={`h-6 w-6 rounded-md flex items-center justify-center text-[13px] shrink-0 ${BADGE_STYLES[section.color]}`}
                                                        aria-hidden
                                                    >
                                                        {item.icon}
                                                    </span>
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
                <div className="fixed bottom-20 md:bottom-6 left-1/2 -translate-x-1/2 z-50 bg-brand-dark text-white text-sm font-medium px-4 py-2.5 rounded-full shadow-lg">
                    {toast}
                </div>
            )}

            {searching && <SearchOverlay onClose={() => setSearching(false)} />}
            <DialogHost />
        </div>
    );
}
