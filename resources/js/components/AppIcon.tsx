import { SVGProps } from 'react';

type Props = SVGProps<SVGSVGElement> & { name: string };

export default function AppIcon({ name, ...props }: Props) {
    const common = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };
    const shapes: Record<string, React.ReactNode> = {
        grid: <><rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/></>,
        today: <><path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="17" rx="2"/><path d="m8 15 2.2 2.2L16 11.5"/></>,
        tasks: <><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m7 12 3 3 7-7"/></>,
        users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></>,
        paw: <><circle cx="8" cy="9" r="2"/><circle cx="16" cy="9" r="2"/><circle cx="5" cy="14" r="2"/><circle cx="19" cy="14" r="2"/><path d="M8 19c0-2.2 1.8-4 4-4s4 1.8 4 4c0 1.7-1.5 2.5-4 2.5S8 20.7 8 19Z"/></>,
        activity: <path d="M3 12h4l2.5-7 5 14 2.5-7h4"/>,
        file: <><path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 12h6M9 16h6"/></>,
        alert: <><path d="M12 3 2.5 20h19z"/><path d="M12 9v4M12 17h.01"/></>,
        shield: <><path d="M12 2 4 5v6c0 5 3.4 8.5 8 11 4.6-2.5 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></>,
        folder: <path d="M3 6h7l2 2h9v12H3z"/>,
        leave: <><path d="M5 21c2-5 6-8 12-9"/><path d="M6 15c-2-4 0-8 5-11 4 3 5 7 3 10M14 10c2-3 5-4 8-3 0 4-2 7-6 8"/></>,
        directory: <><path d="M4 3h16v18H4zM8 3v18"/><circle cx="14" cy="9" r="2"/><path d="M11 16c.8-2 5.2-2 6 0"/></>,
        megaphone: <><path d="m3 11 15-6v14L3 13zM7 15l1 6h4l-2-7"/></>,
        truck: <><path d="M3 5h11v12H3zM14 9h4l3 3v5h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></>,
        register: <><path d="M5 3h14v18H5zM9 3v4h6V3"/><path d="m8 13 2 2 5-5"/></>,
        moon: <path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z"/>,
        monitor: <><path d="M3 3v18h18"/><path d="m7 16 4-5 3 3 5-7"/></>,
        wrench: <path d="M14 6a4 4 0 0 0-5 5L3 17l4 4 6-6a4 4 0 0 0 5-5l-3 3-4-4z"/>,
        briefcase: <><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 12h18"/></>,
        money: <><circle cx="12" cy="12" r="9"/><path d="M15 8.5c-.7-.6-1.6-1-3-1-2 0-3 1-3 2.3 0 3.4 6 1.7 6 4.7 0 1.3-1.2 2.5-3.3 2.5-1.4 0-2.6-.5-3.4-1.2M12 5v14"/></>,
        receipt: <><path d="M5 3v18l3-2 4 2 4-2 3 2V3l-3 2-4-2-4 2z"/><path d="M9 10h6M9 14h6"/></>,
        mail: <><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></>,
        settings: <><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/></>,
        search: <><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></>,
        bell: <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></>,
        plus: <path d="M12 5v14M5 12h14"/>,
        arrow: <path d="M5 12h14M15 8l4 4-4 4"/>,
        clock: <><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></>,
        lock: <><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></>,
        chart: <><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></>,
        upload: <><path d="M12 16V3M7 8l5-5 5 5"/><path d="M4 14v7h16v-7"/></>,
    };

    return <svg viewBox="0 0 24 24" aria-hidden="true" {...common} {...props}>{shapes[name] ?? shapes.grid}</svg>;
}
