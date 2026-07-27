import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Entry {
    id: number;
    user: string;
    clock_in: string;
    clock_out: string | null;
    break_minutes: number;
    worked_minutes: number | null;
    notes: string | null;
}

interface Props {
    openEntry: Entry | null;
    myEntries: Entry[];
    isManager: boolean;
    weekEntries: Entry[];
}

const hm = (mins: number | null) =>
    mins === null ? '—' : `${Math.floor(mins / 60)}h ${String(mins % 60).padStart(2, '0')}m`;
const time = (d: string) => new Date(d.replace(' ', 'T')).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
const day = (d: string) => new Date(d.replace(' ', 'T')).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

export default function Timeclock({ openEntry, myEntries, isManager, weekEntries }: Props) {
    const [clockingOut, setClockingOut] = useState(false);
    const [breakMinutes, setBreakMinutes] = useState(0);

    return (
        <AppShell title="Time Clock">
            <Head title="Time Clock" />
            <ModuleHero eyebrow="Working time" title="Time clock" description="Clock in, clock out and keep an accurate record of working hours." icon="⏱️" tone="blue" />

            <Card className="mb-4 text-center">
                {openEntry ? (
                    <>
                        <div className="text-sm text-slate-500 font-medium">Clocked in at</div>
                        <div className="text-4xl font-extrabold text-brand-dark my-1">{time(openEntry.clock_in)}</div>
                        <button
                            onClick={() => setClockingOut(true)}
                            className="mt-2 rounded-full bg-red-600 text-white font-bold px-8 py-3"
                        >
                            Clock out
                        </button>
                    </>
                ) : (
                    <>
                        <div className="text-sm text-slate-500 font-medium mb-2">You're not clocked in</div>
                        <button
                            onClick={() => router.post('/timeclock/in')}
                            className="rounded-full bg-status-green text-white font-bold px-8 py-3"
                        >
                            Clock in
                        </button>
                    </>
                )}
            </Card>

            <Card title="My recent shifts" className="mb-4">
                {myEntries.length === 0 && <p className="text-sm text-slate-400">No shifts recorded yet.</p>}
                <ul className="divide-y divide-slate-100 text-sm">
                    {myEntries.map((e) => (
                        <li key={e.id} className="py-2 flex items-center justify-between">
                            <span className="font-semibold">{day(e.clock_in)}</span>
                            <span className="text-slate-500">
                                {time(e.clock_in)} – {e.clock_out ? time(e.clock_out) : '…'}
                                {e.break_minutes > 0 && ` · ${e.break_minutes}m break`}
                            </span>
                            <span className="font-bold text-brand-dark">{hm(e.worked_minutes)}</span>
                        </li>
                    ))}
                </ul>
            </Card>

            {isManager && (
                <Card title="All staff — this week">
                    {weekEntries.length === 0 && <p className="text-sm text-slate-400">No entries this week.</p>}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-slate-400 uppercase">
                                    <th className="py-1 pr-2">Staff</th>
                                    <th className="py-1 pr-2">Day</th>
                                    <th className="py-1 pr-2">In–Out</th>
                                    <th className="py-1 text-right">Worked</th>
                                </tr>
                            </thead>
                            <tbody>
                                {weekEntries.map((e) => (
                                    <tr key={e.id} className="border-t border-slate-100">
                                        <td className="py-2 pr-2 font-semibold">{e.user}</td>
                                        <td className="py-2 pr-2">{day(e.clock_in)}</td>
                                        <td className="py-2 pr-2 text-slate-500">
                                            {time(e.clock_in)} – {e.clock_out ? time(e.clock_out) : '…'}
                                        </td>
                                        <td className="py-2 text-right font-bold">{hm(e.worked_minutes)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            <Modal open={clockingOut} title="Clock out" onClose={() => setClockingOut(false)}>
                <div className="space-y-3">
                    <label className="block text-sm font-medium">
                        Break taken (minutes)
                        <input
                            type="number"
                            min={0}
                            value={breakMinutes}
                            onChange={(e) => setBreakMinutes(Number(e.target.value))}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <button
                        onClick={() =>
                            router.post('/timeclock/out', { break_minutes: breakMinutes }, { onSuccess: () => setClockingOut(false) })
                        }
                        className="w-full rounded-lg bg-red-600 text-white font-bold py-3"
                    >
                        Clock out now
                    </button>
                </div>
            </Modal>
        </AppShell>
    );
}
