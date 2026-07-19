import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import MoodPicker from '../components/MoodPicker';
import { MOOD_EMOJI, Mood } from '../types';

interface Record {
    end_mood: Mood | null;
    session_type: string | null;
    activities: string | null;
    notes: string | null;
    concern: boolean;
    concern_detail: string | null;
}

interface Row {
    id: number;
    name: string;
    arrival_mood: Mood | null;
    record: Record | null;
}

export default function EndOfDay({ date, rows }: { date: string; rows: Row[] }) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [form, setForm] = useState<Record>({
        end_mood: null,
        session_type: '',
        activities: '',
        notes: '',
        concern: false,
        concern_detail: '',
    });

    const doneCount = rows.filter((r) => r.record).length;

    function open(row: Row) {
        setEditing(row);
        setForm({
            end_mood: row.record?.end_mood ?? null,
            session_type: row.record?.session_type ?? '',
            activities: row.record?.activities ?? '',
            notes: row.record?.notes ?? '',
            concern: row.record?.concern ?? false,
            concern_detail: row.record?.concern_detail ?? '',
        });
    }

    function save() {
        if (!editing) return;
        router.post(`/end-of-day/${editing.id}`, { ...form }, { onSuccess: () => setEditing(null) });
    }

    return (
        <AppShell title="End of Day">
            <Head title="End of Day" />

            <p className="text-slate-500 font-medium mb-4">
                {new Date(date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })} —{' '}
                <span className="font-bold text-brand-dark">{doneCount}</span> of {rows.length} recorded
            </p>

            {rows.length === 0 && (
                <Card>
                    <p className="text-slate-500">No members checked in today yet — complete the morning register first.</p>
                </Card>
            )}

            <div className="space-y-2">
                {rows.map((row) => (
                    <Card key={row.id}>
                        <div className="flex items-center gap-3">
                            <div className="flex-1 min-w-0">
                                <div className="font-bold text-brand-dark">
                                    {row.name}
                                    {row.record?.concern && <span className="ml-2" title="Concern flagged">⚠️</span>}
                                </div>
                                <div className="text-sm text-slate-500">
                                    {row.arrival_mood && <>Arrived {MOOD_EMOJI[row.arrival_mood]}</>}
                                    {row.record?.end_mood && <> → left {MOOD_EMOJI[row.record.end_mood]}</>}
                                </div>
                            </div>
                            <button
                                onClick={() => open(row)}
                                className={`shrink-0 rounded-full text-sm font-semibold px-4 py-2.5 ${
                                    row.record ? 'bg-emerald-100 text-emerald-800' : 'bg-brand text-white'
                                }`}
                            >
                                {row.record ? '✓ Edit' : 'Record'}
                            </button>
                        </div>
                    </Card>
                ))}
            </div>

            <Modal open={editing !== null} title={`End of day — ${editing?.name ?? ''}`} onClose={() => setEditing(null)}>
                <div className="space-y-4">
                    <div>
                        <div className="text-sm font-medium mb-2">Mood at end of day</div>
                        <MoodPicker value={form.end_mood} onChange={(m) => setForm({ ...form, end_mood: m })} />
                    </div>
                    <div>
                        <label htmlFor="eod-session" className="text-sm font-medium block mb-1">Session type</label>
                        <input
                            id="eod-session"
                            value={form.session_type ?? ''}
                            onChange={(e) => setForm({ ...form, session_type: e.target.value })}
                            placeholder="e.g. Animal care, Horticulture"
                            className="w-full rounded-lg border border-slate-300 px-3"
                        />
                    </div>
                    <div>
                        <label htmlFor="eod-activities" className="text-sm font-medium block mb-1">Activities participated in</label>
                        <textarea
                            id="eod-activities"
                            value={form.activities ?? ''}
                            onChange={(e) => setForm({ ...form, activities: e.target.value })}
                            className="w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </div>
                    <div>
                        <label htmlFor="eod-notes" className="text-sm font-medium block mb-1">General notes</label>
                        <textarea
                            id="eod-notes"
                            value={form.notes ?? ''}
                            onChange={(e) => setForm({ ...form, notes: e.target.value })}
                            className="w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm font-medium text-red-700">
                        <input
                            type="checkbox"
                            checked={form.concern}
                            onChange={(e) => setForm({ ...form, concern: e.target.checked })}
                            className="rounded border-slate-300"
                        />
                        ⚠️ Flag a concern
                    </label>
                    {form.concern && (
                        <textarea
                            value={form.concern_detail ?? ''}
                            onChange={(e) => setForm({ ...form, concern_detail: e.target.value })}
                            placeholder="Describe the concern — managers will be notified"
                            className="w-full rounded-lg border border-red-300 bg-red-50 p-3"
                            rows={2}
                        />
                    )}
                    <button onClick={save} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        Save record
                    </button>
                </div>
            </Modal>
        </AppShell>
    );
}
