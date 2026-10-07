import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import ModuleHero from '../components/ModuleHero';

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
    actors: { id: number; name: string }[];
    filters: { search?: string; subject?: string; actor?: string; action?: string; from?: string; to?: string };
}

const ACTION_STYLE: Record<string, string> = {
    created: 'bg-emerald-100 text-emerald-800',
    updated: 'bg-blue-100 text-blue-800',
    deleted: 'bg-red-100 text-red-800',
};

export default function AuditLog({ entries, subjects, actors, filters }: Props) {
    const [query, setQuery] = useState({ search: filters.search ?? '', subject: filters.subject ?? '', actor: filters.actor ?? '', action: filters.action ?? '', from: filters.from ?? '', to: filters.to ?? '' });
    const [filtersOpen, setFiltersOpen] = useState(false);
    function search(e: FormEvent) {
        e.preventDefault();
        setFiltersOpen(false);
        router.get('/audit-log', Object.fromEntries(Object.entries(query).filter(([, value]) => value)), { preserveState: true, replace: true });
    }
    function clear() {
        setQuery({ search: '', subject: '', actor: '', action: '', from: '', to: '' });
        router.get('/audit-log');
    }
    return (
        <AppShell title="Audit Log">
            <Head title="Audit Log" />
            <ModuleHero eyebrow="System oversight" title="Audit log" description="A clear, searchable record of important activity across the Hub." icon="🧾" tone="slate" />

            <button type="button" className="audit-log-filter-toggle-4a" onClick={() => setFiltersOpen((open) => !open)} aria-expanded={filtersOpen}>Filters {Object.values(query).some(Boolean) ? '• Active' : ''}<span>{filtersOpen ? '−' : '+'}</span></button>
            <form onSubmit={search} className={`audit-log-filters-4a ${filtersOpen ? 'is-open' : ''}`}>
                <label className="audit-log-search-4a"><span>Search the audit trail</span><input type="search" value={query.search} onChange={(e) => setQuery({ ...query, search: e.target.value })} placeholder="Person, record, change or ID…" /></label>
                <label><span>Actor</span><select value={query.actor} onChange={(e) => setQuery({ ...query, actor: e.target.value })}><option value="">Anyone</option>{actors.map((actor) => <option key={actor.id} value={actor.id}>{actor.name}</option>)}</select></label>
                <label><span>Module</span><select value={query.subject} onChange={(e) => setQuery({ ...query, subject: e.target.value })}><option value="">All modules</option>{subjects.map((subject) => <option key={subject} value={subject}>{subject}</option>)}</select></label>
                <label><span>Action</span><select value={query.action} onChange={(e) => setQuery({ ...query, action: e.target.value })}><option value="">Any action</option><option value="created">Created</option><option value="updated">Updated</option><option value="deleted">Deleted</option></select></label>
                <label><span>From</span><input type="date" value={query.from} onChange={(e) => setQuery({ ...query, from: e.target.value })} /></label>
                <label><span>To</span><input type="date" value={query.to} onChange={(e) => setQuery({ ...query, to: e.target.value })} /></label>
                <div><button type="submit">Search records</button><button type="button" onClick={clear}>Clear</button></div>
            </form>

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
                    {entries.data.length === 0 && <li className="audit-log-empty-4a"><b>No matching audit records</b><span>Try a wider date range or clear one of the filters.</span></li>}
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
