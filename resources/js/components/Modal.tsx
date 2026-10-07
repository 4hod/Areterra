import { ReactNode, useEffect, useId } from 'react';
export default function Modal({ open, title, onClose, children }: { open: boolean; title: string; onClose: () => void; children: ReactNode; }) {
    const titleId = useId();
    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKeyDown);
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => { document.removeEventListener('keydown', onKeyDown); document.body.style.overflow = previousOverflow; };
    }, [open, onClose]);
    if (!open) return null;
    return <div className="hub-modal-backdrop" onClick={onClose}>
        <div role="dialog" aria-modal="true" aria-labelledby={titleId} onClick={e => e.stopPropagation()} className="hub-modal">
            <header><button type="button" onClick={onClose} className="hub-modal-cancel"><span className="hub-modal-cancel-word">Cancel</span><span className="hub-modal-cancel-icon">←</span></button><div><span className="hub-modal-kicker">Areterra Hub</span><h2 id={titleId}>{title}</h2></div><button type="button" onClick={onClose} aria-label="Close" className="hub-modal-close">✕</button></header>
            <div className="hub-modal-body">{children}</div>
        </div>
    </div>;
}
