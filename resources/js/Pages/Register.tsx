import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Modal from '../components/Modal';
import MoodPicker from '../components/MoodPicker';
import { MOOD_EMOJI, Mood } from '../types';

interface Row { id: number; name: string; scheduled: boolean; status: 'expected' | 'present' | 'absent'; absence_reason: string | null; checked_in: boolean; checked_in_at: string | null; arrival_mood: Mood | null; notes: string | null; }
interface Cancellation { date: string; reason: string }
interface Props { date: string; rows: Row[]; others: { id: number; name: string }[]; cancellation: Cancellation | null; }

export default function Register({ date, rows, others, cancellation }: Props) {
    const [editing, setEditing] = useState<Row | null>(null);
    const [mood, setMood] = useState<Mood | null>(null);
    const [notes, setNotes] = useState('');
    const [showOthers, setShowOthers] = useState(false);
    const present = rows.filter((row) => row.checked_in).length;
    const absent = rows.filter((row) => row.status === 'absent').length;
    const outstanding = rows.length - present - absent;
    const percent = rows.length ? Math.round((present / rows.length) * 100) : 0;

    function openCheckIn(row: Row) { setEditing(row); setMood(row.arrival_mood); setNotes(row.notes ?? ''); }

    function markAbsent(row: Row) {
        const reason = window.prompt(`Why is ${row.name} not in today? (leave blank if unknown)`);
        if (reason === null) return;
        router.post(`/register/${row.id}/absent`, { absence_reason: reason || null });
    }

    function cancelDay() {
        const reason = window.prompt('Why is today cancelled? This stands down every session, both transport runs, and all charges.');
        if (!reason) return;
        router.post('/register/cancel-day', { reason });
    }
    function save() {
        if (!editing) return;
        const payload = { arrival_mood: mood, notes };
        if (editing.checked_in) router.put(`/register/${editing.id}`, payload, { onSuccess: () => setEditing(null) });
        else router.post(`/register/${editing.id}/check-in`, payload, { onSuccess: () => setEditing(null) });
    }

    return (
        <AppShell title="Morning Register">
            <Head title="Register" />
            <div className="register-page-4a">
                <section className="register-hero-4a">
                    <div><span className="module-kicker-4a">Morning arrivals</span><h1>Morning Register</h1><p>{new Date(date).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })}</p></div>
                    <div className="register-hero-stats-4a"><article><strong>{present}</strong><span>Checked in</span></article><article><strong>{outstanding}</strong><span>Still expected</span></article><article><strong>{absent}</strong><span>Absent</span></article><article><strong>{percent}%</strong><span>Register complete</span></article></div>
                </section>

                {cancellation ? (
                    <section className="rounded-card bg-red-50 border border-red-200 text-red-800 px-4 py-3 mb-3">
                        <b>Today is cancelled — {cancellation.reason}</b>
                        <p className="text-sm mt-0.5">Sessions and both transport runs are stood down. Nobody has been charged, and this does not count as an absence for anyone.</p>
                    </section>
                ) : (
                    <div className="flex justify-end mb-3">
                        <button onClick={cancelDay} className="rounded-full bg-slate-100 text-slate-700 font-semibold text-xs px-4 py-2">
                            Cancel today
                        </button>
                    </div>
                )}

                <section className="register-progress-card-4a"><div><span>Arrival progress</span><b>{present} of {rows.length} members are in</b></div><div className="register-progress-track-4a"><i style={{ width: `${percent}%` }} /></div></section>

                <section className="register-grid-4a">
                    {rows.map((row) => (
                        <article key={row.id} className={`register-member-card-4a ${row.checked_in ? 'is-present' : ''}`}>
                            <div className="register-member-head-4a"><div className="register-avatar-4a">{row.name.split(' ').map((part) => part.charAt(0)).join('').slice(0, 2)}</div><span>{row.scheduled ? 'Scheduled' : 'Ad-hoc'}</span></div>
                            <h2>{row.name}</h2>
                            <div className="register-status-4a">{row.checked_in ? <><strong>Present</strong><span>Checked in at {row.checked_in_at}{row.arrival_mood ? ` ${MOOD_EMOJI[row.arrival_mood]}` : ''}</span></> : row.status === 'absent' ? <><strong>Absent</strong><span>{row.absence_reason ?? 'No reason recorded'}</span></> : <><strong>Awaiting arrival</strong><span>No check-in recorded yet</span></>}</div>
                            {row.notes && <p className="register-note-4a">“{row.notes}”</p>}
                            <button onClick={() => openCheckIn(row)} className={row.checked_in ? 'is-edit' : ''}>{row.checked_in ? '✓ Edit arrival' : 'Check in member →'}</button>
                            {!row.checked_in && row.status !== 'absent' && !cancellation && (
                                <button onClick={() => markAbsent(row)} className="mt-2 w-full rounded-full bg-slate-100 text-slate-600 font-semibold text-xs px-3 py-2">
                                    Not in today
                                </button>
                            )}
                        </article>
                    ))}
                </section>

                {others.length > 0 && <section className="register-others-4a"><button onClick={() => setShowOthers(!showOthers)}><span>Members not scheduled today</span><b>{others.length}</b><i>{showOthers ? 'Hide' : 'Show'} list</i></button>{showOthers && <div>{others.map((member) => <article key={member.id}><span>{member.name}</span><button onClick={() => openCheckIn({ id: member.id, name: member.name, scheduled: false, checked_in: false, checked_in_at: null, arrival_mood: null, notes: null })}>Ad-hoc check in</button></article>)}</div>}</section>}
            </div>

            <Modal open={editing !== null} title={editing?.name ?? ''} onClose={() => setEditing(null)}>
                <div className="space-y-4"><div><div className="text-sm font-medium mb-2">How did they arrive?</div><MoodPicker value={mood} onChange={setMood} /></div><div><label htmlFor="reg-notes" className="text-sm font-medium block mb-1">Notes</label><textarea id="reg-notes" value={notes} onChange={(e) => setNotes(e.target.value)} className="w-full rounded-lg border border-slate-300 p-3" rows={2} /></div><button onClick={save} className="w-full rounded-lg bg-brand text-white font-bold py-3">{editing?.checked_in ? 'Save arrival details' : 'Check in ✓'}</button></div>
            </Modal>
        </AppShell>
    );
}
