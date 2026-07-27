import type { CSSProperties } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import { ChecklistItem } from '../types';

const LINKS: Record<string, string> = { transport: '/register', register: '/register', moods: '/register', welfare: '/animals', end_of_day: '/end-of-day' };
const ICONS: Record<string, string> = { transport: '🚌', register: '✓', moods: '🙂', welfare: '🐾', end_of_day: '🌙' };

export default function Today({ checklist, date }: { checklist: ChecklistItem[]; date: string }) {
    const done = checklist.filter((item) => item.done).length;
    const percent = checklist.length ? Math.round((done / checklist.length) * 100) : 100;
    const nextItem = checklist.find((item) => !item.done);

    return (
        <AppShell title="Today">
            <Head title="Today" />
            <div className="today-page-4a">
                <section className="today-hero-4a">
                    <div>
                        <span className="module-kicker-4a">Daily command view</span>
                        <h1>{new Date(date).toLocaleDateString('en-GB', { weekday: 'long' })}</h1>
                        <p>{new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</p>
                    </div>
                    <div className="today-progress-4a" style={{ '--today-progress': `${percent}%` } as CSSProperties}>
                        <div><strong>{percent}%</strong><span>{done} of {checklist.length} complete</span></div>
                    </div>
                </section>

                <section className="today-focus-4a">
                    <div><span>Next priority</span><h2>{nextItem ? nextItem.label : 'Everything is complete'}</h2><p>{nextItem ? nextItem.detail : 'The daily workflow is fully completed.'}</p></div>
                    {nextItem && <Link href={LINKS[nextItem.key]} className="module-primary-btn-4a">Open task →</Link>}
                </section>

                <section className="today-flow-4a">
                    {checklist.map((item, index) => (
                        <article key={item.key} className={`today-step-4a ${item.done ? 'is-done' : ''}`}>
                            <div className="today-step-line-4a"><span>{item.done ? '✓' : index + 1}</span></div>
                            <div className="today-step-icon-4a">{ICONS[item.key] ?? '•'}</div>
                            <div className="today-step-copy-4a"><span>{item.done ? 'Completed' : 'Action required'}</span><h3>{item.label}</h3><p>{item.detail}</p></div>
                            {!item.done ? <Link href={LINKS[item.key]} className="today-step-action-4a">Continue</Link> : <div className="today-step-complete-4a">Done</div>}
                        </article>
                    ))}
                </section>

                {done === checklist.length && <section className="today-finished-4a"><span>✓</span><div><h2>All done for today</h2><p>Every item in the daily workflow has been completed.</p></div></section>}
            </div>
        </AppShell>
    );
}
