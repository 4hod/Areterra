import { ReactNode } from 'react';

export default function EmptyState({ icon, text, action }: { icon: string; text: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center text-center py-8">
            <span className="text-3xl mb-2 opacity-40" aria-hidden>{icon}</span>
            <p className="text-sm text-ink/40 mb-2">{text}</p>
            {action}
        </div>
    );
}
