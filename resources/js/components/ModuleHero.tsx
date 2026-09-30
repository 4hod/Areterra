import { ReactNode } from 'react';
import AppIcon from './AppIcon';

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
    const label = `${title} ${eyebrow}`.toLowerCase();
    const iconName = label.includes('animal') || label.includes('welfare') ? 'paw'
        : label.includes('member') || label.includes('staff') || label.includes('directory') ? 'users'
        : label.includes('report') || label.includes('monitor') || label.includes('audit') ? 'chart'
        : label.includes('incident') || label.includes('safeguard') ? 'alert'
        : label.includes('compliance') || label.includes('risk') || label.includes('insurance') || label.includes('polic') ? 'shield'
        : label.includes('document') ? 'folder'
        : label.includes('vehicle') || label.includes('transport') ? 'truck'
        : label.includes('finance') || label.includes('fund') || label.includes('grant') ? 'money'
        : label.includes('invoice') || label.includes('order') ? 'receipt'
        : label.includes('leave') ? 'leave'
        : label.includes('notification') || label.includes('announcement') ? 'bell'
        : label.includes('setting') || label.includes('permission') ? 'settings'
        : label.includes('calendar') || label.includes('activit') ? 'today'
        : label.includes('task') || label.includes('maintenance') ? 'tasks'
        : label.includes('form') || label.includes('referral') ? 'file'
        : icon ? 'grid' : 'grid';

    return (
        <section className={`module-hero module-hero--${tone}`}>
            <div className="module-hero__glow" aria-hidden="true" />
            <div className="module-hero__body">
                <div className="module-hero__icon" aria-hidden="true"><AppIcon name={iconName} /></div>
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
