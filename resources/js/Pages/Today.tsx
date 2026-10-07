import type { CSSProperties } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import { ChecklistItem } from '../types';

const LINKS: Record<string, string> = { transport: '/transport', register: '/register', moods: '/register', welfare: '/animals', end_of_day: '/end-of-day', return_transport: '/transport' };
const ICONS: Record<string, string> = { transport: '🚌', register: '✓', moods: '🙂', welfare: '🌿', end_of_day: '🌙', return_transport: '🏠' };

export default function Today({ checklist, date }: { checklist: ChecklistItem[]; date: string }) {
    const applicableItems = checklist.filter((item) => item.applicable);
    const doneItems = applicableItems.filter((item) => item.done);
    const done = doneItems.length;
    const percent = applicableItems.length ? Math.round((done / applicableItems.length) * 100) : 100;
    const welfareItem = checklist.find((item) => item.key === 'welfare');
    const orderedItems = checklist.filter((item) => item.key !== 'welfare' && item.applicable);
    const notScheduledItems = checklist.filter((item) => !item.applicable);
    const openItems = orderedItems.filter((item) => !item.done);
    const completedItems = orderedItems.filter((item) => item.done);
    const nextItem = welfareItem && !welfareItem.done
        ? welfareItem
        : openItems[0];

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
                        <div><strong>{percent}%</strong><span>{done} of {applicableItems.length} applicable tasks</span></div>
                    </div>
                </section>

                <section className="today-focus-4a">
                    <div><span>Next priority</span><h2>{nextItem ? nextItem.label : 'Everything is complete'}</h2><p>{nextItem ? nextItem.detail : 'The daily workflow is fully completed.'}</p></div>
                    {nextItem && <Link href={LINKS[nextItem.key]} className="module-primary-btn-4a">Open task →</Link>}
                </section>

                <section className="today-flow-4a">
                    {openItems.map((item, index) => (
                        <article key={item.key} className={`today-step-4a ${item.done ? 'is-done' : ''} ${!item.available ? 'is-locked' : ''}`}>
                            <div className="today-step-line-4a"><span>{index + 1}</span></div>
                            <div className="today-step-icon-4a">{ICONS[item.key] ?? '•'}</div>
                            <div className="today-step-copy-4a"><span>{item.done ? 'Completed' : item.available ? 'Action required' : 'Locked'}</span><h3>{item.label}</h3><p>{!item.available ? 'Complete the previous job first' : item.detail}</p></div>
                            {item.available ? <Link href={LINKS[item.key]} className="today-step-action-4a">Continue</Link> : <div className="today-step-locked-4a" aria-label="Locked">🔒</div>}
                        </article>
                    ))}
                </section>

                {(completedItems.length > 0 || notScheduledItems.length > 0 || welfareItem?.done) && (
                    <details className="today-summary-4a">
                        <summary>Completed and not scheduled <span>{completedItems.length + notScheduledItems.length + (welfareItem?.done ? 1 : 0)}</span></summary>
                        <div>
                            {welfareItem?.done && <Link href={LINKS.welfare}><b>✓</b><span><strong>{welfareItem.label}</strong><small>{welfareItem.detail}</small></span><em>Completed</em></Link>}
                            {completedItems.map((item) => <Link key={item.key} href={LINKS[item.key]}><b>✓</b><span><strong>{item.label}</strong><small>{item.detail}</small></span><em>Completed</em></Link>)}
                            {notScheduledItems.map((item) => <div key={item.key}><b>—</b><span><strong>{item.label}</strong><small>{item.detail}</small></span><em>Not scheduled</em></div>)}
                        </div>
                    </details>
                )}

                {done === applicableItems.length && <section className="today-finished-4a"><span>✓</span><div><h2>All applicable work is recorded</h2><p>Tasks that were not scheduled have not been counted as completed.</p></div></section>}
            </div>
        </AppShell>
    );
}
