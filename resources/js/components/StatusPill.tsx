const DOT: Record<string, string> = {
    green: 'bg-status-green',
    amber: 'bg-status-amber',
    red: 'bg-status-red',
    active: 'bg-status-green',
    inactive: 'bg-ink/30',
    'on-leave': 'bg-status-amber',
    archived: 'bg-ink/25',
};

const TEXT: Record<string, string> = {
    green: 'text-status-green',
    amber: 'text-status-amber',
    red: 'text-status-red',
    active: 'text-status-green',
    inactive: 'text-ink/60',
    'on-leave': 'text-status-amber',
    archived: 'text-ink/50',
};

export default function StatusPill({ status, label }: { status: string; label?: string }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full bg-black/[0.03] px-2.5 py-1 text-xs font-semibold capitalize ${
                TEXT[status] ?? 'text-ink/60'
            }`}
        >
            <span className={`h-1.5 w-1.5 rounded-full ${DOT[status] ?? 'bg-ink/30'}`} aria-hidden />
            {label ?? status}
        </span>
    );
}
