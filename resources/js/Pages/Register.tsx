import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import MoodPicker from '../components/MoodPicker';
import { MOOD_EMOJI, Mood } from '../types';

interface Row {
    id: number;
    name: string;
    scheduled: boolean;
    checked_in: boolean;
    checked_in_at: string | null;
    arrival_mood: Mood | null;
    notes: string | null;
}

interface Props {
    date: string;
    rows: Row[];
    others: { id: number; name: string }[];
}

export default function Register({ date, rows, others }: Props) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [mood, setMood] = useState<Mood | null>(null);
    const [notes, setNotes] = useState('');
    const [showOthers, setShowOthers] = useState(false);

    const present = rows.filter((r) => r.checked_in).length;

    function openCheckIn(row: Row) {
        setEditing(row);
        setMood(row.arrival_mood);
        setNotes(row.notes ?? '');
    }

    function save() {
        if (!editing) return;
        const payload = { arrival_mood: mood, notes };
        if (editing.checked_in) {
            router.put(`/register/${editing.id}`, payload, { onSuccess: () => setEditing(null) });
        } else {
            router.post(`/register/${editing.id}/check-in`, payload, { onSuccess: () => setEditing(null) });
        }
    }

    return (
        <AppShell title="Morning Register">
            <Head title="Register" />

            <p className="text-slate-500 font-medium mb-4">
                {new Date(date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })} —{' '}
                <span className="font-bold text-brand-dark">{present}</span> of {rows.length} in
            </p>

            <div className="space-y-2">
                {rows.map((row) => (
                    <Card key={row.id}>
                        <div className="flex items-center gap-3">
                            <div className="h-10 w-10 shrink-0 rounded-full bg-brand/10 text-brand font-bold flex items-center justify-center">
                                {row.name.charAt(0)}
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="font-bold text-brand-dark truncate">
                                    {row.name}
                                    {!row.scheduled && (
                                        <span className="ml-2 text-xs font-semibold text-amber-600">ad-hoc</span>
                                    )}
                                </div>
                                {row.checked_in && (
                                    <div className="text-sm text-slate-500">
                                        In at {row.checked_in_at}
                                        {row.arrival_mood && <span className="ml-1">{MOOD_EMOJI[row.arrival_mood]}</span>}
                                    </div>
                                )}
                            </div>
                            <button
                                onClick={() => openCheckIn(row)}
                                className={`shrink-0 rounded-full text-sm font-semibold px-4 py-2.5 ${
                                    row.checked_in
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-brand text-white'
                                }`}
                            >
                                {row.checked_in ? '✓ In' : 'Check in'}
                            </button>
                        </div>
                    </Card>
                ))}
            </div>

            {others.length > 0 && (
                <div className="mt-4">
                    <button onClick={() => setShowOthers(!showOthers)} className="text-sm font-semibold text-brand">
                        {showOthers ? 'Hide' : 'Show'} members not scheduled today ({others.length})
                    </button>
                    {showOthers && (
                        <div className="mt-2 space-y-2">
                            {others.map((m) => (
                                <Card key={m.id}>
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium text-slate-600">{m.name}</span>
                                        <button
                                            onClick={() =>
                                                openCheckIn({
                                                    id: m.id,
                                                    name: m.name,
                                                    scheduled: false,
                                                    checked_in: false,
                                                    checked_in_at: null,
                                                    arrival_mood: null,
                                                    notes: null,
                                                })
                                            }
                                            className="rounded-full bg-slate-200 text-slate-700 text-sm font-semibold px-4 py-2"
                                        >
                                            Check in
                                        </button>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            )}

            <Modal open={editing !== null} title={editing?.name ?? ''} onClose={() => setEditing(null)}>
                <div className="space-y-4">
                    <div>
                        <div className="text-sm font-medium mb-2">How did they arrive?</div>
                        <MoodPicker value={mood} onChange={setMood} />
                    </div>
                    <div>
                        <label htmlFor="reg-notes" className="text-sm font-medium block mb-1">
                            Notes
                        </label>
                        <textarea
                            id="reg-notes"
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            className="w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </div>
                    <button onClick={save} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        {editing?.checked_in ? 'Save' : 'Check in ✓'}
                    </button>
                </div>
            </Modal>
        </AppShell>
    );
}
