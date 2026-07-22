import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
    name: string;
    role: string;
    canManageSettings: boolean;
}

export default function AccountMenu({ name, role, canManageSettings }: Props) {
    const [open, setOpen] = useState(false);
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

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen((o) => !o)}
                className="hidden md:flex items-center gap-2 rounded-full pl-1 pr-2.5 py-1 hover:bg-black/5"
            >
                <span className="h-8 w-8 rounded-full bg-brand/15 text-brand-dark font-bold text-xs flex items-center justify-center">
                    {name.charAt(0)}
                </span>
                <span className="text-sm font-semibold text-ink/80 max-w-[100px] truncate">{name}</span>
                <span className={`text-ink/40 text-xs transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden>▾</span>
            </button>

            {open && (
                <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl border border-black/[0.06] py-1.5 z-50" style={{ boxShadow: 'var(--shadow-card)' }}>
                    <div className="px-3.5 py-2 border-b border-black/[0.05]">
                        <div className="font-semibold text-sm text-ink/85 truncate">{name}</div>
                        <div className="text-xs text-ink/40 capitalize">{role.replace('_', ' ')}</div>
                    </div>
                    <Link href="/account" onClick={() => setOpen(false)} className="flex items-center gap-2.5 px-3.5 py-2 text-sm text-ink/75 hover:bg-black/[0.03]">
                        <span aria-hidden>👤</span> My profile
                    </Link>
                    <Link href="/notifications" onClick={() => setOpen(false)} className="flex items-center gap-2.5 px-3.5 py-2 text-sm text-ink/75 hover:bg-black/[0.03]">
                        <span aria-hidden>🔔</span> Notification settings
                    </Link>
                    {canManageSettings && (
                        <Link href="/settings" onClick={() => setOpen(false)} className="flex items-center gap-2.5 px-3.5 py-2 text-sm text-ink/75 hover:bg-black/[0.03]">
                            <span aria-hidden>⚙️</span> Hub settings
                        </Link>
                    )}
                    <div className="my-1 border-t border-black/[0.05]" />
                    <button
                        onClick={() => router.post('/logout')}
                        className="w-full flex items-center gap-2.5 px-3.5 py-2 text-sm text-status-red hover:bg-black/[0.03] text-left"
                    >
                        <span aria-hidden>⎋</span> Log out
                    </button>
                </div>
            )}
        </div>
    );
}
