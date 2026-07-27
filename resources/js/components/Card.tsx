import { ReactNode } from 'react';

export default function Card({
    title,
    action,
    children,
    className = '',
}: {
    title?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`hub-card ${className}`}>
            {(title || action) && (
                <header className="hub-card-header">
                    {title && <h2>{title}</h2>}
                    {action && <div className="hub-card-action">{action}</div>}
                </header>
            )}
            <div className="hub-card-body">{children}</div>
        </section>
    );
}
