import { ReactNode } from 'react';

export default function StatTile({
    icon,
    value,
    label,
    color,
    children,
}: {
    icon: string;
    value: ReactNode;
    label: string;
    color: string;
    children?: ReactNode;
}) {
    return (
        <div className="rounded-card p-4" style={{ background: color, boxShadow: '0 8px 20px -8px rgba(0,0,0,0.25)' }}>
            <div className="flex items-center justify-between mb-3">
                <span className="h-9 w-9 rounded-lg bg-white/25 flex items-center justify-center text-lg">{icon}</span>
            </div>
            <div className="text-3xl font-extrabold text-white leading-none">{value}</div>
            <div className="text-xs font-bold uppercase tracking-wide text-white/80 mt-1.5">{label}</div>
            {children}
        </div>
    );
}
