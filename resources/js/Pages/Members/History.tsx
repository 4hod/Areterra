import { Head, Link } from '@inertiajs/react';
import AppShell from '../../components/AppShell';
import Breadcrumbs from '../../components/Breadcrumbs';
import Card from '../../components/Card';
import { MOOD_EMOJI, Mood } from '../../types';
import ModuleHero from '../../components/ModuleHero';

interface EodRow {
    id: number;
    date: string;
    arrival_mood: Mood | null;
    end_mood: Mood | null;
    session_type: string | null;
    activities: string | null;
    food_intake: string | null;
    fluid_intake: string | null;
    toileting_notes: string | null;
    medication_given: boolean;
    medication_notes: string | null;
    incident: boolean;
    incident_detail: string | null;
    photos: string[];
    notes: string | null;
    concern: boolean;
    concern_detail: string | null;
    author: string | null;
}

interface AttRow {
    id: number;
    date: string;
    checked_in: boolean;
    checked_in_at: string | null;
    arrival_mood: Mood | null;
    notes: string | null;
}

interface StaffNoteRow {
    id: number;
    note_type: string;
    note: string;
    author_name: string | null;
    noted_at: string | null;
}

interface PageLink { url: string | null; label: string; active: boolean }
interface Paginated<T> { data: T[]; links: PageLink[] }

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const INTAKE_LABEL: Record<string, string> = { good: 'Good', some: 'Some', poor: 'Poor', refused: 'Refused' };
const NOTE_TYPE_LABELS: Record<string, string> = {
    end_of_day: 'End of Day Record',
    general: 'General note',
    concern: 'Concern note',
    progress: 'Progress note',
    handover: 'Handover note',
};

function Pagination({ links }: { links: PageLink[] }) {
    return (
        <div className="flex flex-wrap gap-1 mt-3">
            {links.map((l, i) =>
                l.url ? (
                    <Link
                        key={i}
                        href={l.url}
                        preserveScroll
                        className={`rounded px-2.5 py-1 text-xs font-semibold ${l.active ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'}`}
                        dangerouslySetInnerHTML={{ __html: l.label }}
                    />
                ) : null,
            )}
        </div>
    );
}

export default function History({
    member,
    endOfDay,
    attendance,
    staffNotes,
}: {
    member: { id: number; name: string };
    endOfDay: Paginated<EodRow>;
    attendance: Paginated<AttRow>;
    staffNotes: Paginated<StaffNoteRow>;
}) {
    return (
        <AppShell title={`${member.name} — full history`}>
            <Head title={`${member.name} — history`} />
            <ModuleHero eyebrow="Member journey" title="History" description="A chronological view of support, attendance and meaningful events." icon="🕰️" tone="teal" />

            <Breadcrumbs items={[
                { label: 'Members', href: '/members' },
                { label: member.name, href: `/members/${member.id}` },
                { label: 'Full history' },
            ]} />

            <Card title="Staff notes" className="mb-4">
                {staffNotes.data.length === 0 && <p className="text-sm text-slate-400">No staff notes recorded.</p>}
                <ul className="divide-y divide-slate-100">
                    {staffNotes.data.map((note) => (
                        <li key={note.id} className="py-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="font-semibold text-sm text-brand-dark">
                                    {NOTE_TYPE_LABELS[note.note_type] ?? note.note_type}
                                </span>
                                <span className="text-xs text-slate-400">
                                    {note.noted_at ? fmt(note.noted_at) : 'Date not recorded'}
                                    {note.author_name && ` · ${note.author_name}`}
                                </span>
                            </div>
                            <p className="text-sm mt-1 whitespace-pre-wrap">{note.note}</p>
                        </li>
                    ))}
                </ul>
                <Pagination links={staffNotes.links} />
            </Card>

            <Card title="End-of-day history" className="mb-4">
                {endOfDay.data.length === 0 && <p className="text-sm text-slate-400">No end-of-day records yet.</p>}
                <ul className="divide-y divide-slate-100">
                    {endOfDay.data.map((r) => (
                        <li key={r.id} className="py-3">
                            <div className="flex items-center justify-between">
                                <span className="font-semibold text-sm">
                                    {fmt(r.date)}
                                    {r.concern && <span className="ml-2" title="Safeguarding concern flagged">⚠️</span>}
                                    {r.incident && <span className="ml-1" title="Incident logged">🩹</span>}
                                </span>
                                <span className="text-lg">
                                    {r.arrival_mood && MOOD_EMOJI[r.arrival_mood]}
                                    {r.end_mood && <> → {MOOD_EMOJI[r.end_mood]}</>}
                                </span>
                            </div>
                            {r.session_type && <div className="text-sm text-slate-500 mt-1">{r.session_type}</div>}
                            {r.author && <div className="text-xs text-slate-400">by {r.author}</div>}
                            {r.activities && <div className="text-sm text-slate-500">{r.activities}</div>}

                            {(r.food_intake || r.fluid_intake) && (
                                <div className="text-xs text-slate-500 mt-1">
                                    {r.food_intake && <>Food: {INTAKE_LABEL[r.food_intake]} </>}
                                    {r.fluid_intake && <>· Fluids: {INTAKE_LABEL[r.fluid_intake]}</>}
                                </div>
                            )}
                            {r.toileting_notes && <div className="text-xs text-slate-500">Toileting: {r.toileting_notes}</div>}
                            {r.medication_given && (
                                <div className="text-xs text-slate-500">Medication given{r.medication_notes && `: ${r.medication_notes}`}</div>
                            )}
                            {r.incident && r.incident_detail && (
                                <div className="text-sm text-amber-700 mt-1 bg-amber-50 rounded-lg p-2">🩹 {r.incident_detail}</div>
                            )}
                            {r.concern && r.concern_detail && (
                                <div className="text-sm text-red-700 mt-1 bg-red-50 rounded-lg p-2">⚠️ {r.concern_detail}</div>
                            )}
                            {r.notes && <div className="text-sm mt-1">{r.notes}</div>}
                            {r.photos.length > 0 && (
                                <div className="flex gap-2 mt-2 flex-wrap">
                                    {r.photos.map((p) => (
                                        <img key={p} src={p} className="h-16 w-16 rounded-lg object-cover" />
                                    ))}
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
                <Pagination links={endOfDay.links} />
            </Card>

            <Card title="Attendance history">
                {attendance.data.length === 0 && <p className="text-sm text-slate-400">No attendance recorded yet.</p>}
                <ul className="divide-y divide-slate-100 text-sm">
                    {attendance.data.map((a) => (
                        <li key={a.id} className="py-2 flex items-center justify-between">
                            <span className="font-medium">{fmt(a.date)}</span>
                            <span>
                                {a.checked_in ? `✓ Attended${a.checked_in_at ? ` (${a.checked_in_at.slice(0, 5)})` : ''}` : '—'}
                                {a.arrival_mood && <span className="ml-1">{MOOD_EMOJI[a.arrival_mood]}</span>}
                            </span>
                        </li>
                    ))}
                </ul>
                <Pagination links={attendance.links} />
            </Card>
        </AppShell>
    );
}
