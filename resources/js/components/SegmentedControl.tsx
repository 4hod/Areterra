interface SegmentedControlProps<T extends string> { options: { value: T; label: string }[]; value: T; onChange: (value: T) => void; }
export default function SegmentedControl<T extends string>({ options, value, onChange }: SegmentedControlProps<T>) {
    return <div className="hub-segmented">{options.map(o => (
        <button key={o.value} onClick={() => onChange(o.value)} className={value === o.value ? 'is-active' : ''}>{o.label}</button>
    ))}</div>;
}
