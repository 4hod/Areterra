import { Head, Link } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import { ChecklistItem } from '../types';

const LINKS: Record<string, string> = {
    transport: '/register',
    register: '/register',
    moods: '/register',
    welfare: '/animals',
    end_of_day: '/end-of-day',
};

export default function Today({ checklist, date }: { checklist: ChecklistItem[]; date: string }) {
    const done = checklist.filter((c) => c.done).length;

    return (
        <AppShell title="Today">
            <Head title="Today" />

            <p className="text-slate-500 font-medium mb-4">
                {new Date(date).toLocaleDateString('en-GB', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                })}{' '}
                — {done} of {checklist.length} complete
            </p>

            <div className="space-y-3">
                {checklist.map((item, i) => (
                    <Card key={item.key} className={item.done ? 'opacity-70' : ''}>
                        <div className="flex items-center gap-4">
                            <span
                                className={`h-9 w-9 shrink-0 rounded-full flex items-center justify-center font-bold text-white ${
                                    item.done ? 'bg-status-green' : 'bg-slate-300'
                                }`}
                            >
                                {item.done ? '✓' : i + 1}
                            </span>
                            <div className="flex-1 min-w-0">
                                <div className={`font-bold ${item.done ? 'text-slate-400 line-through' : 'text-brand-dark'}`}>
                                    {item.label}
                                </div>
                                <div className="text-sm text-slate-500">{item.detail}</div>
                            </div>
                            {!item.done && (
                                <Link
                                    href={LINKS[item.key]}
                                    className="shrink-0 rounded-full bg-brand text-white text-sm font-semibold px-4 py-2"
                                >
                                    Go →
                                </Link>
                            )}
                        </div>
                    </Card>
                ))}
            </div>

            {done === checklist.length && (
                <Card className="mt-4 text-center border-l-4 border-l-status-green">
                    <div className="text-4xl mb-1">🎉</div>
                    <div className="font-bold text-brand-dark">All done for today!</div>
                </Card>
            )}
        </AppShell>
    );
}
