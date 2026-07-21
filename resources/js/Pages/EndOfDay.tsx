import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import MoodPicker from '../components/MoodPicker';
import { MOOD_EMOJI, Mood } from '../types';

type Intake = 'good' | 'some' | 'poor' | 'refused';

const INTAKE_OPTIONS: { value: Intake; label: string }[] = [
    { value: 'good', label: 'Good' },
    { value: 'some', label: 'Some' },
    { value: 'poor', label: 'Poor' },
    { value: 'refused', label: 'Refused' },
];

interface Record {
    end_mood: Mood | null;
    session_type: string | null;
    activities: string | null;
    food_intake: Intake | null;
    fluid_intake: Intake | null;
    toileting_notes: string | null;
    medication_given: boolean;
    medication_notes: string | null;
    incident: boolean;
    incident_detail: string | null;
    photos: string[];
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

const BLANK: Record = {
    end_mood: null,
    session_type: '',
    activities: '',
    food_intake: null,
    fluid_intake: null,
    toileting_notes: '',
    medication_given: false,
    medication_notes: '',
    incident: false,
    incident_detail: '',
    photos: [],
    notes: '',
    concern: false,
    concern_detail: '',
};

function IntakePicker({ value, onChange }: { value: Intake | null; onChange: (v: Intake) => void }) {
    return (
        <div className="flex gap-1.5">
            {INTAKE_OPTIONS.map((o) => (
                <button
                    type="button"
                    key={o.value}
                    onClick={() => onChange(o.value)}
                    className={`flex-1 rounded-lg text-xs font-semibold py-2 ${
                        value === o.value ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'
                    }`}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

export default function EndOfDay({ date, rows }: { date: string; rows: Row[] }) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [form, setForm] = useState<Record>(BLANK);
    const [newPhotos, setNewPhotos] = useState<File[]>([]);
    const [removePhotos, setRemovePhotos] = useState<string[]>([]);
    const photoInput = useRef<HTMLInputElement>(null);

    const doneCount = rows.filter((r) => r.record).length;

    function open(row: Row) {
        setEditing(row);
        setNewPhotos([]);
        setRemovePhotos([]);
        setForm({
            ...BLANK,
            ...row.record,
            photos: row.record?.photos ?? [],
        });
    }

    function save() {
        if (!editing) return;
        router.post(
            `/end-of-day/${editing.id}`,
            { ...form, photos: newPhotos, remove_photos: removePhotos },
            { forceFormData: true, onSuccess: () => setEditing(null) },
        );
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
                                    {row.record?.incident && <span className="ml-1" title="Incident logged">🩹</span>}
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

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <div className="text-sm font-medium mb-1">Food intake</div>
                            <IntakePicker value={form.food_intake} onChange={(v) => setForm({ ...form, food_intake: v })} />
                        </div>
                        <div>
                            <div className="text-sm font-medium mb-1">Fluid intake</div>
                            <IntakePicker value={form.fluid_intake} onChange={(v) => setForm({ ...form, fluid_intake: v })} />
                        </div>
                    </div>

                    <div>
                        <label htmlFor="eod-toileting" className="text-sm font-medium block mb-1">Toileting notes</label>
                        <textarea
                            id="eod-toileting"
                            value={form.toileting_notes ?? ''}
                            onChange={(e) => setForm({ ...form, toileting_notes: e.target.value })}
                            className="w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </div>

                    <div>
                        <label className="flex items-center gap-2 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={form.medication_given}
                                onChange={(e) => setForm({ ...form, medication_given: e.target.checked })}
                                className="rounded border-slate-300"
                            />
                            Medication given
                        </label>
                        {form.medication_given && (
                            <textarea
                                value={form.medication_notes ?? ''}
                                onChange={(e) => setForm({ ...form, medication_notes: e.target.value })}
                                placeholder="What was given and when"
                                className="mt-2 w-full rounded-lg border border-slate-300 p-3"
                                rows={2}
                            />
                        )}
                    </div>

                    <div>
                        <label className="flex items-center gap-2 text-sm font-medium text-amber-700">
                            <input
                                type="checkbox"
                                checked={form.incident}
                                onChange={(e) => setForm({ ...form, incident: e.target.checked })}
                                className="rounded border-slate-300"
                            />
                            🩹 Log an incident (bump, trip, minor injury — not a safeguarding concern)
                        </label>
                        {form.incident && (
                            <textarea
                                value={form.incident_detail ?? ''}
                                onChange={(e) => setForm({ ...form, incident_detail: e.target.value })}
                                placeholder="What happened, action taken"
                                className="mt-2 w-full rounded-lg border border-amber-300 bg-amber-50 p-3"
                                rows={2}
                            />
                        )}
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

                    <div>
                        <div className="text-sm font-medium mb-1">Photos</div>
                        <div className="flex flex-wrap gap-2 mb-2">
                            {form.photos.filter((p) => !removePhotos.includes(p)).map((p) => (
                                <div key={p} className="relative">
                                    <img src={p} className="h-16 w-16 rounded-lg object-cover" />
                                    <button
                                        type="button"
                                        onClick={() => setRemovePhotos([...removePhotos, p])}
                                        className="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-red-600 text-white text-xs font-bold"
                                    >
                                        ✕
                                    </button>
                                </div>
                            ))}
                            {newPhotos.map((f, i) => (
                                <img key={i} src={URL.createObjectURL(f)} className="h-16 w-16 rounded-lg object-cover" />
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => photoInput.current?.click()}
                            className="rounded-full bg-slate-100 text-slate-600 font-semibold text-xs px-3 py-2"
                        >
                            + Add photo
                        </button>
                        <input
                            ref={photoInput}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={(e) => setNewPhotos([...newPhotos, ...Array.from(e.target.files ?? [])])}
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm font-medium text-red-700">
                        <input
                            type="checkbox"
                            checked={form.concern}
                            onChange={(e) => setForm({ ...form, concern: e.target.checked })}
                            className="rounded border-slate-300"
                        />
                        ⚠️ Flag a safeguarding concern
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
