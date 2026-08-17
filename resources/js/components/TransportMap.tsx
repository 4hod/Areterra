import { useEffect, useRef } from 'react';

declare global {
    interface Window {
        L: any;
    }
}

export interface MapStop {
    id: number;
    name: string;
    address: string | null;
    lat: number | null;
    lng: number | null;
}

export default function TransportMap({ stops }: { stops: MapStop[] }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<any>(null);

    const pinned = stops.filter((s) => s.lat !== null && s.lng !== null);

    useEffect(() => {
        if (!containerRef.current || !window.L || pinned.length === 0) return;

        const L = window.L;
        const map = L.map(containerRef.current, { scrollWheelZoom: false });
        mapRef.current = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        const markers = pinned.map((s) => {
            const marker = L.marker([s.lat, s.lng]).addTo(map);
            const directionsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(s.address ?? `${s.lat},${s.lng}`)}`;
            marker.bindPopup(
                `<b>${s.name}</b><br>${s.address ?? ''}<br><a href="${directionsUrl}" target="_blank" rel="noreferrer">Open in Google Maps →</a>`,
            );
            return marker;
        });

        if (markers.length === 1) {
            map.setView([pinned[0].lat, pinned[0].lng], 13);
        } else {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.2));
        }

        return () => {
            map.remove();
            mapRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(pinned.map((s) => s.id))]);

    if (pinned.length === 0) {
        return (
            <div className="rounded-card bg-ink/[0.03] flex items-center justify-center h-56 text-sm text-ink/40">
                No pinned addresses yet — pins appear once addresses are geocoded.
            </div>
        );
    }

    return <div ref={containerRef} className="rounded-card overflow-hidden h-72 w-full" />;
}
