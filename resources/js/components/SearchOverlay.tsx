import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface ResultGroup {
    [group: string]: { title: string; url: string }[];
}

export default function SearchOverlay({ onClose }: { onClose: () => void }) {
    const [q, setQ] = useState('');
    const [results, setResults] = useState<ResultGroup>({});
    const [loading, setLoading] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        inputRef.current?.focus();
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

    return (
        <div className="fixed inset-0 z-50 bg-black/40 flex items-start justify-center pt-20 px-4" onClick={onClose}>
            <div className="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[70vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                <div className="p-3 border-b border-slate-100 flex items-center gap-2">
                    <span className="text-slate-400">🔍</span>
                    <input
                        ref={inputRef}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search members, animals, announcements, documents…"
                        className="flex-1 outline-none text-sm py-2"
                    />
                    <button onClick={onClose} className="text-slate-400 text-sm font-semibold px-2">Esc</button>
                </div>

                <div className="p-2">
                    {loading && <p className="text-sm text-slate-400 p-3">Searching…</p>}
                    {!loading && q.trim().length >= 2 && groups.length === 0 && (
                        <p className="text-sm text-slate-400 p-3">No results for "{q}".</p>
                    )}
                    {groups.map(([group, items]) => (
                        <div key={group} className="mb-2">
                            <div className="text-xs font-bold text-slate-400 uppercase px-3 py-1">{group}</div>
                            {items.map((item, i) => (
                                <Link
                                    key={i}
                                    href={item.url}
                                    onClick={onClose}
                                    className="block px-3 py-2 rounded-lg hover:bg-slate-50 text-sm font-medium text-brand-dark"
                                >
                                    {item.title}
                                </Link>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
