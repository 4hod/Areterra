interface TabBarProps<T extends string> {
    tabs: readonly T[];
    active: T;
    onChange: (tab: T) => void;
}

export default function TabBar<T extends string>({ tabs, active, onChange }: TabBarProps<T>) {
    return (
        <div className="mb-5 border-b border-black/[0.06] overflow-x-auto">
            <div className="flex gap-5 min-w-max px-0.5">
                {tabs.map((t) => (
                    <button
                        key={t}
                        onClick={() => onChange(t)}
                        className={`relative shrink-0 whitespace-nowrap pb-2.5 pt-1 text-sm font-semibold transition-colors ${
                            active === t ? 'text-brand-dark' : 'text-ink/45 hover:text-ink/70'
                        }`}
                    >
                        {t}
                        {active === t && (
                            <span
                                className="absolute left-0 right-0 -bottom-px h-[3px] rounded-full bg-accent"
                                aria-hidden
                            />
                        )}
                    </button>
                ))}
            </div>
        </div>
    );
}
