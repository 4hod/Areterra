import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface RelatedLink {
    href: string;
    label: string;
    icon?: string;
}

export default function RecordHeader({
    eyebrow,
    title,
    description,
    backHref,
    backLabel,
    leading,
    status,
    actions,
    related = [],
}: {
    eyebrow: string;
    title: ReactNode;
    description?: ReactNode;
    backHref: string;
    backLabel: string;
    leading?: ReactNode;
    status?: ReactNode;
    actions?: ReactNode;
    related?: RelatedLink[];
}) {
    return (
        <section className="record-header">
            <Link href={backHref} className="record-header-back">← {backLabel}</Link>
            <div className="record-header-main">
                {leading && <div className="record-header-leading">{leading}</div>}
                <div className="record-header-copy">
                    <span>{eyebrow}</span>
                    <h1>{title}</h1>
                    {description && <p>{description}</p>}
                </div>
                {actions && <div className="record-header-actions">{actions}</div>}
            </div>
            {(status || related.length > 0) && (
                <div className="record-header-context">
                    {status && <div className="record-header-status">{status}</div>}
                    {related.length > 0 && (
                        <nav aria-label="Related records">
                            <span>Related</span>
                            {related.map((item) => (
                                <Link key={`${item.href}-${item.label}`} href={item.href}>{item.icon} {item.label}</Link>
                            ))}
                        </nav>
                    )}
                </div>
            )}
        </section>
    );
}

