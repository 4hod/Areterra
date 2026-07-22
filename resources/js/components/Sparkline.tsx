export default function Sparkline({ values, color = 'var(--color-brand)' }: { values: number[]; color?: string }) {
    if (values.length < 2) return null;

    const w = 100;
    const h = 28;
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const step = w / (values.length - 1);

    const points = values.map((v, i) => `${i * step},${h - ((v - min) / range) * h}`).join(' ');

    return (
        <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-7" preserveAspectRatio="none" aria-hidden>
            <polyline points={points} fill="none" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}
