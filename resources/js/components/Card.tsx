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
        <section className={`bg-white rounded-card shadow-sm border border-slate-200/60 ${className}`}>
            {(title || action) && (
                <header className="flex items-center justify-between px-4 pt-4 pb-2">
                    {title && <h2 className="font-bold text-brand-dark">{title}</h2>}
                    {action}
                </header>
            )}
            <div className="p-4 pt-2">{children}</div>
        </section>
    );
}
