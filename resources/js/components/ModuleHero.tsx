import { ReactNode } from 'react';

type Tone = 'teal' | 'blue' | 'purple' | 'amber' | 'rose' | 'green' | 'slate';

interface ModuleHeroProps {
    eyebrow: string;
    title: string;
    description: string;
    icon: string;
    tone?: Tone;
    actions?: ReactNode;
}

export default function ModuleHero({ eyebrow, title, description, icon, tone = 'teal', actions }: ModuleHeroProps) {
    return (
        <section className={`module-hero module-hero--${tone}`}>
            <div className="module-hero__glow" aria-hidden="true" />
            <div className="module-hero__body">
                <div className="module-hero__icon" aria-hidden="true">{icon}</div>
                <div className="module-hero__copy">
                    <div className="module-hero__eyebrow">{eyebrow}</div>
                    <h1>{title}</h1>
                    <p>{description}</p>
                </div>
            </div>
            {actions && <div className="module-hero__actions">{actions}</div>}
        </section>
    );
}
