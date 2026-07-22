interface SegmentedControlProps<T extends string> {
    options: { value: T; label: string }[];
    value: T;
    onChange: (value: T) => void;
}

export default function SegmentedControl<T extends string>({ options, value, onChange }: SegmentedControlProps<T>) {
    return (
        <div className="inline-flex flex-wrap gap-0.5 rounded-full bg-ink/[0.05] p-1 mb-4">
            {options.map((o) => (
                <button
                    key={o.value}
                    onClick={() => onChange(o.value)}
                    className={`rounded-full px-3.5 py-1.5 text-xs font-semibold whitespace-nowrap transition-all ${
                        value === o.value
                            ? 'bg-white text-brand-dark shadow-sm'
                            : 'text-ink/50 hover:text-ink/80'
                    }`}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}
