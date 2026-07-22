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
            className={`bg-white rounded-card border border-black/[0.04] ${className}`}
            style={{ boxShadow: 'var(--shadow-card)' }}
        >
            {(title || action) && (
                <header className="flex items-center justify-between px-5 pt-4 pb-3">
                    {title && (
                        <h2 className="heading-stroke font-display font-semibold text-brand-dark text-[1.05rem] tracking-tight">
                            {title}
                        </h2>
                    )}
                    {action}
                </header>
            )}
            <div className="p-5 pt-2">{children}</div>
        </section>
    );
}
