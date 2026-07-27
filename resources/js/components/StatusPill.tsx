const TONE: Record<string,string> = { green:'green',active:'green',amber:'amber','on-leave':'amber',red:'red',inactive:'neutral',archived:'neutral' };
export default function StatusPill({ status, label }: { status: string; label?: string }) {
    return <span className={`hub-status-pill tone-${TONE[status] ?? 'neutral'}`}><i />{label ?? status}</span>;
}
