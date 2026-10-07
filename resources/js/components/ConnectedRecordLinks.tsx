import { Link, usePage } from '@inertiajs/react';
import { SharedProps } from '../types';

export default function ConnectedRecordLinks({ type, id, compact = false }: { type: 'member' | 'animal' | 'vehicle'; id: number; compact?: boolean }) {
    const caps = usePage<SharedProps>().props.auth.user?.capabilities ?? [];
    const query = `about=${type}&id=${id}`;
    const links = [
        { href: `/tasks?${query}`, label: 'Tasks', icon: '☑', show: true },
        { href: `/forms?${query}`, label: 'Forms', icon: '📝', show: true },
        { href: `/documents?${query}`, label: 'Documents', icon: '📁', show: caps.includes('view_documents') },
        { href: `/incidents?${query}`, label: 'Incidents', icon: '⚠', show: caps.includes('report_incidents') },
    ].filter((link) => link.show);

    return <div className={`flex flex-wrap gap-2 ${compact ? 'mt-3 border-t border-slate-100 pt-3' : 'mb-4 rounded-2xl border border-sky-100 bg-sky-50/60 p-3'}`}>
        {!compact && <span className="mr-1 self-center text-xs font-extrabold uppercase tracking-wide text-slate-500">Connected records</span>}
        {links.map((link) => <Link key={link.href} href={link.href} className="rounded-full border border-sky-200 bg-white px-3 py-1.5 text-xs font-bold text-brand shadow-sm">
            {link.icon} {link.label}
        </Link>)}
    </div>;
}
