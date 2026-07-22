import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { getRecentlyViewed, RecentItem } from '../utils/recentlyViewed';

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
            return;
        }
        setLoading(true);
        const t = setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((data) => setResults(data.results ?? {}))
                .finally(() => setLoading(false));
        }, 250);
        return () => clearTimeout(t);
    }, [q]);

    const groups = Object.entries(results);
    const showingDefault = q.trim().length < 2;

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-start justify-center pt-20 px-4" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[70vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                <div className="p-3 border-b border-black/[0.06] flex items-center gap-2">
                    <span className="text-ink/35">🔍</span>
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
                                        <span className="text-xs text-ink/30">{item.type === 'Member' ? '👤' : '🐾'}</span>
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
