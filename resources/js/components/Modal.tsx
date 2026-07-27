import { ReactNode } from 'react';
export default function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode; }) {
    if (!open) return null;
    return <div className="hub-modal-backdrop" onClick={onClose}>
        <div role="dialog" aria-modal="true" aria-label={title} onClick={e => e.stopPropagation()} className="hub-modal">
            <header><div><span className="hub-modal-kicker">Areterra Hub</span><h2>{title}</h2></div><button type="button" onClick={onClose} aria-label="Close">✕</button></header>
            <div className="hub-modal-body">{children}</div>
        </div>
    </div>;
}
