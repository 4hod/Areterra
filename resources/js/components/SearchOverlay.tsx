import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { getRecentlyViewed, RecentItem } from '../utils/recentlyViewed';
import AppIcon from './AppIcon';

interface ResultGroup {
    [group: string]: { title: string; url: string }[];
}

function ResultSkeleton() {
    return (
        <div className="p-2 space-y-2">
            {[0, 1, 2].map((i) => (
                <div key={i} className="h-9 rounded-lg skeleton" />
            ))}
        </div>
    );
}

export default function SearchOverlay({ onClose }: { onClose: () => void }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState<ResultGroup>({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [recent, setRecent] = useState<RecentItem[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        inputRef.current?.focus();
        setRecent(getRecentlyViewed());
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    useEffect(() => {
        if (q.trim().length < 2) {
            setResults({});
            setError(null);
            return;
        }
        setLoading(true);
        setError(null);
        const controller = new AbortController();
        const t = setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(q)}`, {
                credentials: 'same-origin',
                signal: controller.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => {
                    if (!r.ok || !r.headers.get('content-type')?.includes('application/json')) {
                        throw new Error(`Search returned ${r.status}`);
                    }
                    return r.json();
                })
                .then((data) => setResults(data.results ?? {}))
                .catch((reason) => {
                    if (reason.name !== 'AbortError') setError('Search is temporarily unavailable. Please try again.');
                })
                .finally(() => {
                    if (!controller.signal.aborted) setLoading(false);
                });
        }, 250);
        return () => {
            clearTimeout(t);
            controller.abort();
        };
    }, [q]);

    const groups = Object.entries(results);
    const showingDefault = q.trim().length < 2;

    return (
        <div className="search-overlay" onClick={onClose}>
            <div className="search-dialog" onClick={(e) => e.stopPropagation()}>
                <div className="search-dialog-head">
                    <AppIcon name="search" />
                    <input
                        ref={inputRef}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search members, animals, announcements, documents…"
                        className="flex-1 outline-none text-sm py-2"
                    />
                    <button onClick={onClose} className="text-ink/35 text-sm font-semibold px-2">Esc</button>
                </div>

                {showingDefault ? (
                    <div className="p-2">
                        {recent.length > 0 ? (
                            <>
                                <div className="text-xs font-bold text-ink/35 uppercase px-3 py-1.5">Recently viewed</div>
                                {recent.map((item, i) => (
                                    <Link
                                        key={i}
                                        href={item.url}
                                        onClick={onClose}
                                        className="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-black/[0.03] text-sm font-medium text-brand-dark"
                                    >
                                        <AppIcon name={item.type === 'Member' ? 'users' : 'paw'} className="h-4 w-4 text-slate-400" />
                                        {item.title}
                                    </Link>
                                ))}
                            </>
                        ) : (
                            <p className="text-sm text-ink/35 p-3">Start typing to search, or visit a few members and animals to see them here.</p>
                        )}
                    </div>
                ) : loading ? (
                    <ResultSkeleton />
                ) : error ? (
                    <p className="p-5 text-sm font-medium text-red-600" role="alert">{error}</p>
                ) : (
                    <div className="p-2">
                        {groups.length === 0 && (
                            <p className="text-sm text-ink/35 p-3">No results for "{q}".</p>
                        )}
                        {groups.map(([group, items]) => (
                            <div key={group} className="mb-2">
                                <div className="text-xs font-bold text-ink/35 uppercase px-3 py-1">{group}</div>
                                {items.map((item, i) => (
                                    <Link
                                        key={i}
                                        href={item.url}
                                        onClick={onClose}
                                        className="block px-3 py-2 rounded-lg hover:bg-black/[0.03] text-sm font-medium text-brand-dark"
                                    >
                                        {item.title}
                                    </Link>
                                ))}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
