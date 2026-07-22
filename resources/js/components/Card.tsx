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
        <section
            className={`bg-white rounded-card border border-black/[0.06] overflow-hidden ${className}`}
            style={{ boxShadow: 'var(--shadow-card)' }}
        >
            {(title || action) && (
                <header className="flex items-center justify-between px-4 py-3 bg-brand/[0.04] border-b border-black/[0.05]">
                    {title && <h2 className="font-bold text-brand-dark text-[0.95rem]">{title}</h2>}
                    {action}
                </header>
            )}
            <div className="p-4">{children}</div>
        </section>
    );
}
