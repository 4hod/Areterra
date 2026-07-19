import { ReactNode } from 'react';

// Desktop: centred dialog. Mobile: bottom sheet sliding up, 92vh max (SPEC.md).
export default function Modal({
    open,
    title,
    onClose,
    children,
}: {
    open: boolean;
    title: string;
    onClose: () => void;
    children: ReactNode;
}) {
    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/40"
            onClick={onClose}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onClick={(e) => e.stopPropagation()}
                className="w-full sm:max-w-lg bg-white rounded-t-2xl sm:rounded-card shadow-xl max-h-[92vh] overflow-y-auto"
            >
                <header className="sticky top-0 bg-white flex items-center justify-between px-5 py-4 border-b border-slate-100">
                    <h2 className="font-bold text-brand-dark">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close"
                        className="h-11 w-11 rounded-full hover:bg-slate-100 text-slate-500 text-xl"
                    >
                        ✕
                    </button>
                </header>
                <div className="p-5">{children}</div>
            </div>
        </div>
    );
}
