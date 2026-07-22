import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Notif {
    id: string;
    title: string;
    body: string;
    url: string;
    read: boolean;
    created_at: string;
}

export default function NotificationBell({ unreadCount }: { unreadCount: number }) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [notifications, setNotifications] = useState<Notif[]>([]);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function onClick(e: MouseEvent) {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        }
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') setOpen(false);
        }
        document.addEventListener('mousedown', onClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, []);

    function toggle() {
        if (!open) {
            setLoading(true);
            fetch('/notifications/recent', { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((data) => setNotifications(data.notifications ?? []))
                .finally(() => setLoading(false));
        }
        setOpen((o) => !o);
    }

    function openNotif(n: Notif) {
        if (!n.read) {
            router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true });
        }
        setOpen(false);
        router.visit(n.url);
    }

    return (
        <div ref={ref} className="relative">
            <button
                onClick={toggle}
                aria-label="Notifications"
                className="relative hidden md:flex h-10 w-10 rounded-full hover:bg-black/5 items-center justify-center text-lg"
            >
                🔔
                {unreadCount > 0 && (
                    <span className="absolute top-1 right-1.5 h-4 min-w-4 px-1 rounded-full bg-status-red text-white text-[10px] font-bold flex items-center justify-center">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl border border-black/[0.06] z-50 overflow-hidden" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <div className="px-4 py-2.5 border-b border-black/[0.05] font-semibold text-sm text-ink/85">
                        Notifications
                    </div>
                    <div className="max-h-80 overflow-y-auto">
                        {loading && <p className="text-sm text-ink/40 text-center py-6">Loading…</p>}
                        {!loading && notifications.length === 0 && (
                            <div className="text-center py-8">
                                <span className="text-2xl block mb-1 opacity-40" aria-hidden>🔔</span>
                                <p className="text-sm text-ink/40">Nothing to show yet.</p>
                            </div>
                        )}
                        {notifications.map((n) => (
                            <button
                                key={n.id}
                                onClick={() => openNotif(n)}
                                className="w-full text-left px-4 py-2.5 hover:bg-black/[0.03] border-b border-black/[0.03] last:border-b-0"
                            >
                                <div className={`text-sm font-semibold ${n.read ? 'text-ink/45' : 'text-brand-dark'}`}>
                                    {!n.read && <span className="text-brand mr-1">●</span>}
                                    {n.title}
                                </div>
                                <div className="text-xs text-ink/40 truncate">{n.body} · {n.created_at}</div>
                            </button>
                        ))}
                    </div>
                    <Link
                        href="/notifications"
                        onClick={() => setOpen(false)}
                        className="block text-center text-sm font-semibold text-brand py-2.5 border-t border-black/[0.05] hover:bg-black/[0.02]"
                    >
                        Notification settings
                    </Link>
                </div>
            )}
        </div>
    );
}
