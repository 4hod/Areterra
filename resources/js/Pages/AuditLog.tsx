import { Head, Link, router } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Entry {
    id: number;
    user: string;
    action: string;
    subject_type: string;
    subject_id: number;
    changes: Record<string, unknown> | null;
    created_at: string;
}

interface Props {
    entries: {
        data: Entry[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    subjects: string[];
    filter: string | null;
}

const ACTION_STYLE: Record<string, string> = {
    created: 'bg-emerald-100 text-emerald-800',
    updated: 'bg-blue-100 text-blue-800',
    deleted: 'bg-red-100 text-red-800',
};

export default function AuditLog({ entries, subjects, filter }: Props) {
    return (
        <AppShell title="Audit Log">
            <Head title="Audit Log" />

            <select
                value={filter ?? ''}
                onChange={(e) => router.get('/audit-log', e.target.value ? { subject: e.target.value } : {})}
                className="rounded-lg border border-slate-300 px-3 bg-white mb-4"
            >
                <option value="">All record types</option>
                {subjects.map((s) => (
                    <option key={s} value={s}>{s}</option>
                ))}
            </select>

            <Card>
                <ul className="divide-y divide-slate-100 text-sm">
                    {entries.data.map((e) => (
                        <li key={e.id} className="py-2">
                            <div className="flex items-center justify-between gap-2">
                                <span>
                                    <b>{e.user}</b>{' '}
                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${ACTION_STYLE[e.action]}`}>
                                        {e.action}
                                    </span>{' '}
                                    <span className="text-slate-500">
                                        {e.subject_type} #{e.subject_id}
                                    </span>
                                </span>
                                <span className="text-xs text-slate-400 shrink-0">
                                    {new Date(e.created_at.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                </span>
                            </div>
                            {e.changes && Object.keys(e.changes).length > 0 && (
                                <div className="text-xs text-slate-400 mt-0.5 truncate">
                                    {Object.entries(e.changes).map(([k, v]) => `${k}: ${String(v)}`).join(' · ')}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
                <div className="flex flex-wrap gap-1 mt-3">
                    {entries.links.map((l, i) =>
                        l.url ? (
                            <Link
                                key={i}
                                href={l.url}
                                className={`rounded px-2.5 py-1 text-xs font-semibold ${l.active ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ) : null,
                    )}
                </div>
            </Card>
        </AppShell>
    );
}
