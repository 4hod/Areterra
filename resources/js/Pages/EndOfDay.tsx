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
        <AppShell title="End of Day">
            <Head title="End of Day" />
            <div className="eod-page-4a">
                <section className="eod-hero-4a">
                    <div><span className="module-kicker-4a">Daily records and handover</span><h1>End of Day</h1><p>{new Date(date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })}</p></div>
                    <div className="eod-completion-4a"><strong>{doneCount}</strong><span>of {rows.length} completed</span><div><i style={{ width: `${rows.length ? Math.round((doneCount / rows.length) * 100) : 0}%` }} /></div></div>
                </section>

                {rows.length === 0 && <div className="module-empty-4a"><span>🌙</span><h2>No members to record yet</h2><p>Complete the morning register first, then today’s attendees will appear here.</p></div>}

                {rows.length > 0 && <section className="eod-summary-4a"><article><span>Records complete</span><strong>{doneCount}</strong></article><article><span>Still outstanding</span><strong>{rows.length - doneCount}</strong></article><article><span>Concerns flagged</span><strong>{rows.filter((row) => row.record?.concern).length}</strong></article><article><span>Incidents logged</span><strong>{rows.filter((row) => row.record?.incident).length}</strong></article></section>}

                <section className="eod-grid-4a">
                    {rows.map((row) => (
                        <article key={row.id} className={`eod-member-card-4a ${row.record ? 'is-complete' : ''}`}>
                            <div className="eod-card-head-4a"><div className="eod-avatar-4a">{row.name.split(' ').map((part) => part.charAt(0)).join('').slice(0, 2)}</div><div className="eod-flags-4a">{row.record?.incident && <span title="Incident logged">🩹 Incident</span>}{row.record?.concern && <span className="is-concern" title="Concern flagged">⚠ Concern</span>}</div></div>
                            <h2>{row.name}</h2>
                            <div className="eod-mood-journey-4a"><div><span>Arrival</span><strong>{row.arrival_mood ? MOOD_EMOJI[row.arrival_mood] : '—'}</strong></div><i>→</i><div><span>Departure</span><strong>{row.record?.end_mood ? MOOD_EMOJI[row.record.end_mood] : '—'}</strong></div></div>
                            <div className="eod-record-state-4a">{row.record ? <><strong>Record complete</strong><span>{row.record.session_type || 'Daily record saved'}</span></> : <><strong>Record required</strong><span>Add activities, outcomes and departure details.</span></>}</div>
                            <button onClick={() => open(row)} className={row.record ? 'is-edit' : ''}>{row.record ? '✓ Review and edit' : 'Complete daily record →'}</button>
                        </article>
                    ))}
                </section>
            </div>

            <Modal open={editing !== null} title={`End of day — ${editing?.name ?? ''}`} onClose={() => setEditing(null)}>
                <div className="space-y-4">
                    <div><div className="text-sm font-medium mb-2">Mood at end of day</div><MoodPicker value={form.end_mood} onChange={(m) => setForm({ ...form, end_mood: m })} /></div>
                    <div><label htmlFor="eod-session" className="text-sm font-medium block mb-1">Session type</label><input id="eod-session" value={form.session_type ?? ''} onChange={(e) => setForm({ ...form, session_type: e.target.value })} placeholder="e.g. Animal care, Horticulture" className="w-full rounded-lg border border-slate-300 px-3" /></div>
                    <div><label htmlFor="eod-activities" className="text-sm font-medium block mb-1">Activities participated in</label><textarea id="eod-activities" value={form.activities ?? ''} onChange={(e) => setForm({ ...form, activities: e.target.value })} className="w-full rounded-lg border border-slate-300 p-3" rows={2} /></div>
                    <div className="grid grid-cols-2 gap-3"><div><div className="text-sm font-medium mb-1">Food intake</div><IntakePicker value={form.food_intake} onChange={(v) => setForm({ ...form, food_intake: v })} /></div><div><div className="text-sm font-medium mb-1">Fluid intake</div><IntakePicker value={form.fluid_intake} onChange={(v) => setForm({ ...form, fluid_intake: v })} /></div></div>
                    <div><label htmlFor="eod-toileting" className="text-sm font-medium block mb-1">Toileting notes</label><textarea id="eod-toileting" value={form.toileting_notes ?? ''} onChange={(e) => setForm({ ...form, toileting_notes: e.target.value })} className="w-full rounded-lg border border-slate-300 p-3" rows={2} /></div>
                    <div><label className="flex items-center gap-2 text-sm font-medium"><input type="checkbox" checked={form.medication_given} onChange={(e) => setForm({ ...form, medication_given: e.target.checked })} className="rounded border-slate-300" />Medication given</label>{form.medication_given && <textarea value={form.medication_notes ?? ''} onChange={(e) => setForm({ ...form, medication_notes: e.target.value })} placeholder="What was given and when" className="mt-2 w-full rounded-lg border border-slate-300 p-3" rows={2} />}</div>
                    <div><label className="flex items-center gap-2 text-sm font-medium text-amber-700"><input type="checkbox" checked={form.incident} onChange={(e) => setForm({ ...form, incident: e.target.checked })} className="rounded border-slate-300" />🩹 Log an incident</label>{form.incident && <textarea value={form.incident_detail ?? ''} onChange={(e) => setForm({ ...form, incident_detail: e.target.value })} placeholder="What happened, action taken" className="mt-2 w-full rounded-lg border border-amber-300 bg-amber-50 p-3" rows={2} />}</div>
                    <div><label htmlFor="eod-notes" className="text-sm font-medium block mb-1">General notes</label><textarea id="eod-notes" value={form.notes ?? ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} className="w-full rounded-lg border border-slate-300 p-3" rows={2} /></div>
                    <div><div className="text-sm font-medium mb-1">Photos</div><div className="flex flex-wrap gap-2 mb-2">{form.photos.filter((photo) => !removePhotos.includes(photo)).map((photo) => <div key={photo} className="relative"><img src={photo} className="h-16 w-16 rounded-lg object-cover" /><button type="button" onClick={() => setRemovePhotos([...removePhotos, photo])} className="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-red-600 text-white text-xs font-bold">✕</button></div>)}{newPhotos.map((file, index) => <img key={index} src={URL.createObjectURL(file)} className="h-16 w-16 rounded-lg object-cover" />)}</div><button type="button" onClick={() => photoInput.current?.click()} className="rounded-full bg-slate-100 text-slate-600 font-semibold text-xs px-3 py-2">+ Add photo</button><input ref={photoInput} type="file" accept="image/*" multiple className="hidden" onChange={(e) => setNewPhotos([...newPhotos, ...Array.from(e.target.files ?? [])])} /></div>
                    <label className="flex items-center gap-2 text-sm font-medium text-red-700"><input type="checkbox" checked={form.concern} onChange={(e) => setForm({ ...form, concern: e.target.checked })} className="rounded border-slate-300" />⚠️ Flag a safeguarding concern</label>
                    {form.concern && <textarea value={form.concern_detail ?? ''} onChange={(e) => setForm({ ...form, concern_detail: e.target.value })} placeholder="Describe the concern — managers will be notified" className="w-full rounded-lg border border-red-300 bg-red-50 p-3" rows={2} />}
                    <button onClick={save} className="w-full rounded-lg bg-brand text-white font-bold py-3">Save record</button>
                </div>
            </Modal>
        </AppShell>
    );
}
