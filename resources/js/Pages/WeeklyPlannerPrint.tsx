import { Head, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { SharedProps } from '../types';

interface ActivityRow {
    id: number;
    title: string;
    start_time: string | null;
    description: string | null;
    user: string;
}

interface Day {
    date: string;
    label: string;
    short: string;
    activities: ActivityRow[];
}

export default function WeeklyPlannerPrint({ weekStart, weekEnd, days }: { weekStart: string; weekEnd: string; days: Day[] }) {
    const { branding } = usePage<SharedProps>().props;

    useEffect(() => {
        const t = setTimeout(() => window.print(), 400);
        return () => clearTimeout(t);
    }, []);

    const rangeLabel = `${new Date(weekStart).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} – ${new Date(weekEnd).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`;

    return (
        <div className="p-8 bg-white min-h-screen text-black">
            <Head title={`Weekly Planner — ${rangeLabel}`}>
                <style>{`@page { size: A4 landscape; margin: 10mm; } @media print { .no-print { display: none } }`}</style>
            </Head>

            <div className="flex items-start justify-between mb-1">
                {branding.logoUrl ? (
                    <img src={branding.logoUrl} alt={branding.orgName} className="h-10 object-contain object-left" />
                ) : (
                    <div className="text-xl font-extrabold underline">{branding.orgName}</div>
                )}
                <button onClick={() => window.print()} className="no-print rounded bg-slate-800 text-white text-sm font-semibold px-4 py-2">
                    🖨 Print
                </button>
            </div>

            <h1 className="text-center text-xl font-extrabold underline mt-2 mb-4">Weekly Planner — {rangeLabel}</h1>

            <table className="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        {days.map((d) => (
                            <th key={d.date} className="border border-slate-300 bg-brand-dark text-white p-2 w-[14.28%] align-top">
                                <div className="font-bold">{d.label}</div>
                                <div className="font-normal opacity-80">{d.short}</div>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        {days.map((d) => (
                            <td key={d.date} className="border border-slate-300 align-top p-2" style={{ height: '150mm' }}>
                                {d.activities.length === 0 && <span className="text-slate-300">—</span>}
                                <ul className="space-y-2">
                                    {d.activities.map((a) => (
                                        <li key={a.id}>
                                            <div className="font-bold">
                                                {a.start_time && `${a.start_time} `}
                                                {a.title}
                                            </div>
                                            {a.description && <div className="text-slate-600">{a.description}</div>}
                                        </li>
                                    ))}
                                </ul>
                            </td>
                        ))}
                    </tr>
                </tbody>
            </table>

            <div className="text-[10px] text-slate-400 mt-3 text-center">
                {branding.orgName} · Registered charity No. 1196211 · Printed {new Date().toLocaleDateString('en-GB')}
            </div>
        </div>
    );
}
