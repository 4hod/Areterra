import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppShell from '../components/AppShell';
import ModuleHero from '../components/ModuleHero';
import { getRecentlyViewed } from '../utils/recentlyViewed';

interface ResultGroup {
    [group: string]: { title: string; url: string }[];
}

export default function SearchHub() {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ResultGroup>({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);
    const recent = getRecentlyViewed();

    useEffect(() => {
        if (query.trim().length < 2) {
            setResults({});
            setLoading(false);
            setError(false);
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setError(false);
        const timer = window.setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => {
                    if (!response.ok) throw new Error('Search failed');
                    return response.json();
                })
                .then((data) => setResults(data.results ?? {}))
                .catch((reason: unknown) => {
                    if (reason instanceof DOMException && reason.name === 'AbortError') return;
                    setError(true);
                })
                .finally(() => !controller.signal.aborted && setLoading(false));
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const groups = Object.entries(results);

    return (
        <AppShell title="Search">
            <Head title="Search" />
            <ModuleHero eyebrow="Find anything" title="Search the Hub" description="Find people, animals, documents, tasks, incidents and business records from one place." icon="🔍" tone="slate" />

            <section className="search-workspace">
                <label htmlFor="hub-search-page">What are you looking for?</label>
                <div className="search-workspace-input">
                    <span aria-hidden>🔍</span>
                    <input
                        id="hub-search-page"
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search names, records, documents or tasks…"
                    />
                </div>

                {query.trim().length < 2 ? (
                    <div className="search-workspace-results">
                        <h2>Recently viewed</h2>
                        {recent.length === 0 ? <p>Type at least two characters to search across the Hub.</p> : recent.map((item) => (
                            <Link key={`${item.type}-${item.url}`} href={item.url} className="search-result-row">
                                <span>{item.type === 'Member' ? '👤' : '🐾'}</span>
                                <strong>{item.title}</strong>
                                <small>{item.type}</small>
                            </Link>
                        ))}
                    </div>
                ) : loading ? (
                    <p className="search-workspace-message">Searching…</p>
                ) : error ? (
                    <p className="search-workspace-message text-red-700">Search is temporarily unavailable.</p>
                ) : (
                    <div className="search-workspace-results">
                        {groups.length === 0 && <p>No results for “{query}”.</p>}
                        {groups.map(([group, items]) => (
                            <section key={group}>
                                <h2>{group}</h2>
                                {items.map((item) => (
                                    <Link key={`${group}-${item.url}-${item.title}`} href={item.url} className="search-result-row">
                                        <span aria-hidden>→</span>
                                        <strong>{item.title}</strong>
                                    </Link>
                                ))}
                            </section>
                        ))}
                    </div>
                )}
            </section>
        </AppShell>
    );
}

