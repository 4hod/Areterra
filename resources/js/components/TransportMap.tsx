import { useEffect, useRef } from 'react';
import * as L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export interface MapStop {
    id: number;
    name: string;
    address: string | null;
    lat: number | null;
    lng: number | null;
}

export default function TransportMap({ stops, activeStopId }: { stops: MapStop[]; activeStopId?: number | null }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const mapRef = useRef<any>(null);

    const pinned = stops.filter(
        (stop): stop is MapStop & { lat: number; lng: number } => stop.lat !== null && stop.lng !== null,
    );

    useEffect(() => {
        if (!containerRef.current || pinned.length === 0) return;

        const map = L.map(containerRef.current, { scrollWheelZoom: false });
        mapRef.current = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);

        const markers = pinned.map((s, index) => {
            const isActive = s.id === activeStopId;
            const icon = L.divIcon({
                className: `transport-map-pin${isActive ? ' is-active' : ''}`,
                html: `<span><b>${index + 1}</b></span>`,
                iconSize: [36, 44],
                iconAnchor: [18, 44],
                popupAnchor: [0, -40],
            });
            const marker = L.marker([s.lat, s.lng], {
                icon,
                title: `${index + 1}. ${s.name}`,
                alt: `Stop ${index + 1}: ${s.name}`,
            }).addTo(map);
            const directionsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(s.address ?? `${s.lat},${s.lng}`)}`;
            const popup = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = `${index + 1}. ${s.name}${isActive ? ' — next stop' : ''}`;
            popup.append(name);
            if (s.address) {
                const address = document.createElement('div');
                address.textContent = s.address;
                popup.append(address);
            }
            const directions = document.createElement('a');
            directions.href = directionsUrl;
            directions.target = '_blank';
            directions.rel = 'noreferrer';
            directions.textContent = 'Open in Google Maps →';
            popup.append(directions);
            marker.bindPopup(popup);
            return marker;
        });

        if (markers.length === 1) {
            map.setView([pinned[0].lat, pinned[0].lng], 13);
        } else {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.2));
        }
        const activeIndex = pinned.findIndex((stop) => stop.id === activeStopId);
        if (activeIndex >= 0) markers[activeIndex]?.openPopup();

        return () => {
            map.remove();
            mapRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [activeStopId, JSON.stringify(pinned.map((s) => [s.id, s.name, s.address, s.lat, s.lng]))]);

    if (pinned.length === 0) {
        return (
            <div className="rounded-card bg-ink/[0.03] flex items-center justify-center h-56 text-sm text-ink/40">
                No pinned addresses yet — pins appear once addresses are geocoded.
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-2" aria-label="Mapped transport stops">
                {pinned.map((stop, index) => (
                    <span
                        key={stop.id}
                        className={`transport-map-key${stop.id === activeStopId ? ' is-active' : ''}`}
                    >
                        {index + 1}. {stop.name}{stop.id === activeStopId ? ' · Next' : ''}
                    </span>
                ))}
            </div>
            <div ref={containerRef} className="rounded-card overflow-hidden h-72 w-full" />
        </div>
    );
}
