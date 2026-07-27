import { ReactNode } from 'react';

export default function StatTile({ icon, value, label, color, children }: {
    icon: string; value: ReactNode; label: string; color: string; children?: ReactNode;
}) {
    return (
        <div className="hub-stat-tile" style={{ '--tile-accent': color } as React.CSSProperties}>
            <div className="hub-stat-icon">{icon}</div>
            <div className="hub-stat-value">{value}</div>
            <div className="hub-stat-label">{label}</div>
            {children && <div className="hub-stat-extra">{children}</div>}
        </div>
    );
}
