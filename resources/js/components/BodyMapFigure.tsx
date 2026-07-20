interface Marker {
    view: 'front' | 'back';
    x: number;
    y: number;
    note?: string | null;
}

// Simple front/back body outline. Coordinates are percentages of the viewBox,
// so markers survive any rendering size.
export default function BodyMapFigure({
    view,
    markers,
    onPlace,
}: {
    view: 'front' | 'back';
    markers: Marker[];
    onPlace?: (x: number, y: number) => void;
}) {
    function handleClick(e: React.MouseEvent<SVGSVGElement>) {
        if (!onPlace) return;
        const rect = e.currentTarget.getBoundingClientRect();
        onPlace(
            Math.round(((e.clientX - rect.left) / rect.width) * 1000) / 10,
            Math.round(((e.clientY - rect.top) / rect.height) * 1000) / 10,
        );
    }

    return (
        <svg
            viewBox="0 0 100 220"
            onClick={handleClick}
            className={`w-full max-w-[160px] ${onPlace ? 'cursor-crosshair' : ''}`}
            role="img"
            aria-label={`Body map, ${view} view`}
        >
            {/* head */}
            <circle cx="50" cy="20" r="13" fill="#E2E8F0" stroke="#94A3B8" />
            {/* neck */}
            <rect x="45" y="32" width="10" height="8" fill="#E2E8F0" stroke="#94A3B8" />
            {/* torso */}
            <path d="M30 40 L70 40 L74 95 L62 100 L60 130 L40 130 L38 100 L26 95 Z" fill="#E2E8F0" stroke="#94A3B8" />
            {/* arms */}
            <path d="M30 40 L18 48 L12 95 L20 97 L28 60 Z" fill="#E2E8F0" stroke="#94A3B8" />
            <path d="M70 40 L82 48 L88 95 L80 97 L72 60 Z" fill="#E2E8F0" stroke="#94A3B8" />
            {/* legs */}
            <path d="M40 130 L36 200 L46 200 L49 140 Z" fill="#E2E8F0" stroke="#94A3B8" />
            <path d="M60 130 L64 200 L54 200 L51 140 Z" fill="#E2E8F0" stroke="#94A3B8" />
            <text x="50" y="215" textAnchor="middle" fontSize="9" fill="#94A3B8" fontWeight="bold">
                {view.toUpperCase()}
            </text>

            {markers
                .filter((m) => m.view === view)
                .map((m, i) => (
                    <g key={i}>
                        <circle cx={m.x} cy={(m.y / 100) * 220} r="4.5" fill="#EF4444" stroke="white" strokeWidth="1.5">
                            {m.note && <title>{m.note}</title>}
                        </circle>
                    </g>
                ))}
        </svg>
    );
}
