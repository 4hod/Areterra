import { Link } from '@inertiajs/react';

type DailyStep = 'transport' | 'register' | 'monitoring' | 'end-of-day';
type StepState = 'done' | 'current' | 'todo';

const STEPS: { key: DailyStep; href: string; label: string; icon: string }[] = [
    { key: 'transport', href: '/transport', label: 'Transport', icon: '🚐' },
    { key: 'register', href: '/register', label: 'Register', icon: '📋' },
    { key: 'monitoring', href: '/monitoring', label: 'Welfare', icon: '📊' },
    { key: 'end-of-day', href: '/end-of-day', label: 'End of day', icon: '🌙' },
];

export default function DailyFlowNav({
    active,
    date = new Date().toISOString().slice(0, 10),
    statuses = {},
}: {
    active?: DailyStep;
    date?: string;
    statuses?: Partial<Record<DailyStep, StepState>>;
}) {
    return (
        <section className="daily-flow-nav" aria-label="Daily workflow">
            <div className="daily-flow-nav__heading">
                <div>
                    <span>Today’s connected workflow</span>
                    <strong>Move between daily tasks without losing the day</strong>
                </div>
                <Link href="/today" className="daily-flow-nav__overview">View day overview →</Link>
            </div>
            <div className="daily-flow-nav__steps">
                {STEPS.map((step, index) => {
                    const state = step.key === active ? 'current' : (statuses[step.key] ?? 'todo');
                    return (
                        <Link
                            key={step.key}
                            href={`${step.href}?date=${date}`}
                            className={`daily-flow-nav__step is-${state}`}
                            aria-current={step.key === active ? 'step' : undefined}
                        >
                            <span className="daily-flow-nav__number">{state === 'done' ? '✓' : index + 1}</span>
                            <span className="daily-flow-nav__icon" aria-hidden>{step.icon}</span>
                            <span>
                                <small>{state === 'done' ? 'Complete' : state === 'current' ? 'You are here' : 'Daily task'}</small>
                                <strong>{step.label}</strong>
                            </span>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}
