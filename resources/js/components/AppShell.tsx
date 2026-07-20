import { Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import { SharedProps } from '../types';

interface NavItem {
    href: string;
    label: string;
    icon: string;
    cap?: string;
}

export const NAV_SECTIONS: { title: string | null; items: NavItem[] }[] = [
    {
        title: null,
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
        items: [
            { href: '/members', label: 'Members', icon: '👥', cap: 'view_members' },
            { href: '/animals', label: 'Animals', icon: '🦜', cap: 'view_animals' },
            { href: '/reviews', label: 'Member Reviews', icon: '🔄', cap: 'view_members' },
        ],
    },
    {
        title: 'Staff',
        items: [
            { href: '/announcements', label: 'Announcements', icon: '📢' },
            { href: '/recognition', label: 'Recognition', icon: '🌟' },
            { href: '/leave', label: 'Leave', icon: '🌴', cap: 'request_leave' },
            { href: '/timeclock', label: 'Time Clock', icon: '⏱️', cap: 'own_timeclock' },
            { href: '/directory', label: 'Directory', icon: '📖' },
            { href: '/supervisions', label: 'Supervisions', icon: '🗣️', cap: 'manage_supervisions' },
            { href: '/payroll', label: 'Payroll', icon: '💷', cap: 'manage_payroll' },
        ],
    },
    {
        title: 'Operations',
        items: [
            { href: '/activities', label: 'Activities', icon: '📅', cap: 'log_sessions' },
            { href: '/vehicles', label: 'Vehicles', icon: '🚚', cap: 'view_vehicles' },
            { href: '/referrals', label: 'Referrals', icon: '📨', cap: 'create_members' },
            { href: '/finance', label: 'Finance & Grants', icon: '💰', cap: 'manage_finance' },
            { href: '/invoices', label: 'Invoices', icon: '🧾', cap: 'manage_finance' },
        ],
    },
    {
        title: 'Governance',
        items: [
            { href: '/policies', label: 'Policies', icon: '📜' },
            { href: '/documents', label: 'Documents', icon: '📁' },
            { href: '/risk-assessments', label: 'Risk Assessments', icon: '⚖️' },
            { href: '/compliance', label: 'Compliance', icon: '📋', cap: 'view_all_compliance' },
            { href: '/safeguarding', label: 'Safeguarding', icon: '🛡️', cap: 'access_safeguarding' },
            { href: '/audit', label: 'System Audit', icon: '🩺', cap: 'view_reports' },
            { href: '/notifications', label: 'Notifications', icon: '🔔' },
            { href: '/settings', label: 'Hub Settings', icon: '⚙️', cap: 'manage_settings' },
        ],
    },
];

const MOBILE_NAV: NavItem[] = [
    { href: '/', label: 'Dashboard', icon: '🏠' },
    { href: '/today', label: 'Today', icon: '✅' },
    { href: '/members', label: 'Members', icon: '👥', cap: 'view_members' },
    { href: '/animals', label: 'Animals', icon: '🦜', cap: 'view_animals' },
    { href: '/more', label: 'More', icon: '⋯' },
];

function isActive(href: string, url: string) {
    return href === '/' ? url === '/' : url.startsWith(href);
}

export function allowed(item: NavItem, caps: string[]) {
    return !item.cap || caps.includes(item.cap);
}

export default function AppShell({ title, children }: { title: string; children: ReactNode }) {
    const { auth, flash } = usePage<SharedProps>().props;
    const url = usePage().url;
    const caps = auth.user?.capabilities ?? [];
    const [toast, setToast] = useState<string | null>(null);

    useEffect(() => {
        const message = flash.success ?? flash.error ?? null;
        setToast(message);
        if (message) {
            const t = setTimeout(() => setToast(null), 3500);
            return () => clearTimeout(t);
        }
    }, [flash]);

    return (
        <div className="min-h-screen md:flex">
            {/* Desktop sidebar */}
            <aside className="hidden md:flex md:flex-col w-60 shrink-0 bg-brand-dark text-white min-h-screen sticky top-0 overflow-y-auto">
                <div className="px-5 py-5">
                    <div className="text-xl font-extrabold">Areterra Hub</div>
                    <div className="text-xs text-white/60 mt-1">Animals. People. Purpose.</div>
                </div>
                <nav className="flex-1 px-3 pb-4 space-y-4">
                    {NAV_SECTIONS.map((section, i) => {
                        const items = section.items.filter((item) => allowed(item, caps));
                        if (items.length === 0) return null;
                        return (
                            <div key={i}>
                                {section.title && (
                                    <div className="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-white/40">
                                        {section.title}
                                    </div>
                                )}
                                <div className="space-y-0.5">
                                    {items.map((item) => (
                                        <Link
                                            key={item.href}
                                            href={item.href}
                                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium ${
                                                isActive(item.href, url)
                                                    ? 'bg-brand text-white'
                                                    : 'text-white/75 hover:bg-white/10'
                                            }`}
                                        >
                                            <span aria-hidden>{item.icon}</span>
                                            {item.label}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </nav>
                <div className="p-4 border-t border-white/10 text-sm">
                    <div className="font-semibold truncate">{auth.user?.name}</div>
                    <div className="text-white/50 capitalize text-xs">{auth.user?.role?.replace('_', ' ')}</div>
                    <button
                        onClick={() => router.post('/logout')}
                        className="mt-2 text-white/70 hover:text-white text-xs"
                    >
                        Log out
                    </button>
                </div>
            </aside>

            <div className="flex-1 min-w-0">
                <header className="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between md:px-6">
                    <h1 className="text-lg font-bold text-brand-dark truncate">{title}</h1>
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

            {toast && (
                <div className="fixed bottom-20 md:bottom-6 left-1/2 -translate-x-1/2 z-50 bg-brand-dark text-white text-sm font-medium px-4 py-2.5 rounded-full shadow-lg">
                    {toast}
                </div>
            )}
        </div>
    );
}
