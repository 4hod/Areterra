import { ReactNode } from 'react';
export default function EmptyState({ icon, text, action }: { icon: string; text: string; action?: ReactNode }) {
    return <div className="hub-empty-state"><div className="hub-empty-icon">{icon}</div><p>{text}</p>{action}</div>;
}
