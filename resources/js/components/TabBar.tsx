interface TabBarProps<T extends string> { tabs: readonly T[]; active: T; onChange: (tab: T) => void; }
export default function TabBar<T extends string>({ tabs, active, onChange }: TabBarProps<T>) {
    return <div className="hub-tab-shell"><div className="hub-tab-bar" role="tablist">{tabs.map(t => (
        <button type="button" role="tab" aria-selected={active === t} key={t} onClick={() => onChange(t)} className={active === t ? 'is-active' : ''}>{t}</button>
    ))}</div></div>;
}
