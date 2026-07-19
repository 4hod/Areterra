const STYLES: Record<string, string> = {
    green: 'bg-emerald-100 text-emerald-800',
    amber: 'bg-amber-100 text-amber-800',
    red: 'bg-red-100 text-red-800',
    active: 'bg-emerald-100 text-emerald-800',
    inactive: 'bg-slate-200 text-slate-600',
    'on-leave': 'bg-amber-100 text-amber-800',
    archived: 'bg-slate-200 text-slate-500',
};

export default function StatusPill({ status, label }: { status: string; label?: string }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${
                STYLES[status] ?? 'bg-slate-200 text-slate-600'
            }`}
        >
            {label ?? status}
        </span>
    );
}
