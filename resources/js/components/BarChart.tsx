const DAY_ABBR = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Builds day-of-week labels for the last N values, assuming the last entry is today —
// matches the convention already used by the attendanceTrend query (7 days ending today).
function trailingDayLabels(count: number): string[] {
    const labels: string[] = [];
    for (let i = count - 1; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        labels.push(DAY_ABBR[d.getDay()]);
    }
    return labels;
}

export default function BarChart({ values, highlightLast = true }: { values: number[]; highlightLast?: boolean }) {
    const max = Math.max(...values, 1);
    const labels = trailingDayLabels(values.length);

    return (
        <div className="flex items-end justify-between gap-2 h-32 px-1">
            {values.map((v, i) => {
                const isLast = highlightLast && i === values.length - 1;
                const heightPct = (v / max) * 100;
                return (
                    <div key={i} className="flex-1 flex flex-col items-center gap-1.5 h-full justify-end">
                        <div className="w-full flex items-end justify-center h-full">
                            <div
                                className={`w-full max-w-8 rounded-t-md transition-all ${isLast ? 'bg-brand' : 'bg-ink/15'}`}
                                style={{ height: `${Math.max(heightPct, 4)}%` }}
                                title={`${labels[i]}: ${v}`}
                            />
                        </div>
                        <span className={`text-[11px] font-semibold ${isLast ? 'text-brand-dark' : 'text-ink/35'}`}>{labels[i]}</span>
                    </div>
                );
            })}
        </div>
    );
}
