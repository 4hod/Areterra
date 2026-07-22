export default function ChartCard({ title, value, values, color }: { title: string; value: string | number; values: number[]; color: string }) {
    const w = 100;
    const h = 40;
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const step = w / (values.length - 1 || 1);
    const points = values.map((v, i) => `${i * step},${h - ((v - min) / range) * h}`).join(' ');

    return (
        <div className="rounded-card p-4 text-white" style={{ background: color, boxShadow: '0 8px 20px -8px rgba(0,0,0,0.25)' }}>
            <div className="text-xs font-bold uppercase tracking-wide text-white/75">{title}</div>
            <div className="text-2xl font-extrabold mb-2">{value}</div>
            <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-16" preserveAspectRatio="none" aria-hidden>
                <polyline points={points} fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" opacity="0.9" />
            </svg>
        </div>
    );
}
