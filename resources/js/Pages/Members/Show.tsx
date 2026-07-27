import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '../../components/AppShell';
import BodyMapFigure from '../../components/BodyMapFigure';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import TabBar from '../../components/TabBar';
import { MOOD_EMOJI, Mood } from '../../types';
import { confirmDialog, promptDialog } from '../../utils/dialogs';
import { recordRecentlyViewed } from '../../utils/recentlyViewed';
import ModuleHero from '../../components/ModuleHero';

const DAY_LABELS: Record<number, string> = { 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday' };
const CONSENT_LABELS: Record<string, string> = {
    photos: 'Photography & media',
    outings: 'Outings & trips',
    medication: 'Medication administration',
    data_sharing: 'Data sharing',
    emergency_treatment: 'Emergency treatment',
};

interface EmergencyContact { name: string; relationship?: string; phone?: string }
interface CommsRow { id: number; type: string; direction: string; subject: string | null; summary: string | null; contact_name: string | null; organisation: string | null; date: string; user: string }
interface ContactRow { id: number; name: string; role: string | null; organisation: string | null; email: string | null; phone: string | null; notes: string | null }
interface GoalRow { id: number; title: string; description: string | null; status: string; target_date: string | null; achieved_at: string | null }
interface OutcomeRow { id: number; date: string; outcome: string; goal: string | null; user: string }
interface AlertRow { id: number; type: string; text: string; severity: string }
interface ConsentRow { consent_type: string; granted: boolean; recorded_on: string; notes: string | null }
interface MemberNoteRow { id: number; note_type: string; note: string; author_name: string | null; noted_at: string | null; source: string }

interface Props {
    member: {
        id: number;
        name: string;
        first_name: string;
        last_name: string;
        preferred_name: string | null;
        status: string;
        dob: string | null;
        nhs_number: string | null;
        support_needs: string | null;
        medical_notes: string | null;
        interests: string | null;
        diagnoses: string | null;
        medication: string | null;
        emergency_contacts: EmergencyContact[];
        phone: string | null;
        email: string | null;
        address_line1: string | null;
        address_line2: string | null;
        town: string | null;
        postcode: string | null;
        photo_path: string | null;
        gp_name: string | null;
        gp_practice: string | null;
        gp_phone: string | null;
        settings: { transport_required: boolean; attendance_days: number[]; key_worker: string | null };
    };
    memberNotes: MemberNoteRow[];
    recentAttendance: { id: number; date: string; checked_in: boolean; arrival_mood: Mood | null; notes: string | null }[];
    recentEndOfDay: { id: number; date: string; arrival_mood: Mood | null; end_mood: Mood | null; session_type: string | null; activities: string | null; notes: string | null; concern: boolean; incident: boolean; author: string | null }[];
    abcObservations: { id: number; observed_at: string; antecedent: string | null; behaviour: string; consequence: string | null; wellbeing_score: number | null; concern: boolean; user: string }[];
    bodyMaps: { id: number; recorded_at: string; markers: { view: 'front' | 'back'; x: number; y: number; note?: string | null }[]; notes: string | null; user: string }[];
    commsLog: CommsRow[];
    contacts: ContactRow[];
    goals: GoalRow[];
    outcomes: OutcomeRow[];
    alerts: AlertRow[];
    consents: ConsentRow[];
    canEdit: boolean;
}

const TABS = ['Profile', 'Sessions', '📞 Comms', 'Goals', 'Outcomes', '⚠ Alerts', 'Circle of Care', 'Consents', 'Body Map', 'ABC Obs', 'GP Info', 'Settings'] as const;

const COMMS_ICONS: Record<string, string> = { email: '✉️', phone: '📞', letter: '📮', meeting: '🤝', text: '💬', other: '📝' };
const NOTE_TYPE_LABELS: Record<string, string> = {
    end_of_day: 'End of Day Record',
    general: 'General note',
    concern: 'Concern note',
    progress: 'Progress note',
    handover: 'Handover note',
};

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

function isBirthday(dob: string | null) {
    if (!dob) return false;
    const d = new Date(dob);
    const now = new Date();
    return d.getDate() === now.getDate() && d.getMonth() === now.getMonth();
}

export default function Show(props: Props) {
    const { member, memberNotes, recentAttendance, recentEndOfDay, abcObservations, bodyMaps, commsLog, contacts, goals, outcomes, alerts, consents, canEdit } = props;
    const [tab, setTab] = useState<(typeof TABS)[number]>('Profile');
    const photoInput = useRef<HTMLInputElement>(null);
    const staffNotes = memberNotes.filter((note) => note.note_type !== 'end_of_day');

    useEffect(() => {
        recordRecentlyViewed({ title: member.name, url: `/members/${member.id}`, type: 'Member' });
    }, [member.id]);

    // --- modal state ---
    const [editing, setEditing] = useState(false);
    const [form, setForm] = useState({
        first_name: member.first_name,
        last_name: member.last_name,
        preferred_name: member.preferred_name ?? '',
        status: member.status,
        dob: member.dob ?? '',
        nhs_number: member.nhs_number ?? '',
        support_needs: member.support_needs ?? '',
        diagnoses: member.diagnoses ?? '',
        medication: member.medication ?? '',
        phone: member.phone ?? '',
        email: member.email ?? '',
        address_line1: member.address_line1 ?? '',
        address_line2: member.address_line2 ?? '',
        town: member.town ?? '',
        postcode: member.postcode ?? '',
        gp_name: member.gp_name ?? '',
        gp_practice: member.gp_practice ?? '',
        gp_phone: member.gp_phone ?? '',
        transport_required: member.settings.transport_required,
        attendance_days: member.settings.attendance_days,
    });
    const [postcodeResults, setPostcodeResults] = useState<string[]>([]);

    const [addingComms, setAddingComms] = useState(false);
    const [comms, setComms] = useState({ type: 'phone', direction: 'outbound', subject: '', summary: '', contact_name: '', organisation: '', date: new Date().toISOString().slice(0, 10) });
    const [addingContact, setAddingContact] = useState(false);
    const [contact, setContact] = useState({ name: '', role: '', organisation: '', email: '', phone: '', notes: '' });
    const [addingGoal, setAddingGoal] = useState(false);
    const [goal, setGoal] = useState({ title: '', description: '', target_date: '' });
    const [addingOutcome, setAddingOutcome] = useState(false);
    const [outcome, setOutcome] = useState({ date: new Date().toISOString().slice(0, 10), outcome: '', member_goal_id: '' as string | number });
    const [addingAlert, setAddingAlert] = useState(false);
    const [alert, setAlert] = useState({ type: 'medical', text: '', severity: 'amber' });
    const [addingAbc, setAddingAbc] = useState(false);
    const [abc, setAbc] = useState({ observed_at: new Date().toISOString().slice(0, 16), antecedent: '', behaviour: '', consequence: '', wellbeing_score: '' as string | number, concern: false });
    const [addingMap, setAddingMap] = useState(false);
    const [mapView, setMapView] = useState<'front' | 'back'>('front');
    const [newMarkers, setNewMarkers] = useState<{ view: 'front' | 'back'; x: number; y: number; note: string }[]>([]);
    const [mapNotes, setMapNotes] = useState('');

    // postcodes.io autocomplete (SPEC checklist).
    async function lookupPostcode(value: string) {
        setForm((f) => ({ ...f, postcode: value }));
        if (value.replace(/\s/g, '').length < 3) return setPostcodeResults([]);
        try {
            const res = await fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(value)}/autocomplete`);
            const json = await res.json();
            setPostcodeResults(json.result ?? []);
        } catch {
            setPostcodeResults([]);
        }
    }

    async function pickPostcode(pc: string) {
        setPostcodeResults([]);
        setForm((f) => ({ ...f, postcode: pc }));
        try {
            const res = await fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(pc)}`);
            const json = await res.json();
            if (json.result?.admin_district) {
                setForm((f) => ({ ...f, town: json.result.parish && json.result.parish !== 'unparished area' ? json.result.parish : json.result.admin_ward ?? json.result.admin_district }));
            }
        } catch {
            /* lookup is best-effort */
        }
    }

    function saveProfile() {
        router.put(`/members/${member.id}`, { ...form }, { onSuccess: () => setEditing(false) });
    }

    function uploadPhoto(file: File) {
        router.post(`/members/${member.id}/photo`, { photo: file }, { forceFormData: true });
    }

    function post(url: string, payload: object, close: () => void) {
        router.post(url, payload as never, { onSuccess: close });
    }

    const grantedCount = consents.filter((c) => c.granted).length;

    return (
        <AppShell title={member.name}>
            <Head title={member.name} />
            <ModuleHero eyebrow="Member profile" title="Member record" description="Everything the team needs to understand and support this person well." icon="💚" tone="teal" />

            <Link href="/members" className="inline-block text-sm font-semibold text-brand mb-2">
                ← Back
            </Link>

            {/* Header */}
            <div className="flex items-center gap-4 mb-2">
                <div className="relative">
                    {member.photo_path ? (
                        <img src={member.photo_path} alt={member.name} className="h-16 w-16 rounded-full object-cover" />
                    ) : (
                        <div className="h-16 w-16 rounded-full bg-brand/10 text-brand font-extrabold text-2xl flex items-center justify-center">
                            {member.name.charAt(0)}
                        </div>
                    )}
                    {canEdit && (
                        <>
                            <button
                                onClick={() => photoInput.current?.click()}
                                aria-label="Change photo"
                                className="absolute -bottom-1 -right-1 h-7 w-7 rounded-full bg-brand text-white text-xs flex items-center justify-center ring-2 ring-white"
                            >
                                📷
                            </button>
                            <input
                                ref={photoInput}
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={(e) => e.target.files?.[0] && uploadPhoto(e.target.files[0])}
                            />
                        </>
                    )}
                </div>
                <div>
                    <div className="text-xl font-extrabold text-brand-dark">
                        {member.name}
                        {isBirthday(member.dob) && <span className="ml-2" title="Birthday today!">🎂</span>}
                    </div>
                    <StatusPill status={member.status} />
                </div>
                <div className="ml-auto flex gap-1.5">
                    <button onClick={() => window.print()} className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2">
                        🖨 Print
                    </button>
                    <Link href={`/members/${member.id}/history`} className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2">
                        📜 Full History
                    </Link>
                    {canEdit && (
                        <>
                            <a href={`/members/${member.id}/sar`} target="_blank" className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2">
                                📄 SAR
                            </a>
                            <button onClick={() => setEditing(true)} className="rounded-full bg-brand text-white text-xs font-bold px-3 py-2">
                                ✏️ Edit Profile
                            </button>
                        </>
                    )}
                </div>
            </div>

            {/* Alerts banner */}
            {alerts.length > 0 && (
                <div className="flex flex-wrap gap-1.5 mb-3">
                    {alerts.map((a) => (
                        <span
                            key={a.id}
                            className={`rounded-full px-3 py-1 text-xs font-bold ${
                                a.severity === 'red' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'
                            }`}
                        >
                            ⚠ {a.type}: {a.text}
                        </span>
                    ))}
                </div>
            )}

            {/* Tabs */}
            <TabBar tabs={TABS} active={tab} onChange={setTab} />

            {/* ── Profile ── */}
            {tab === 'Profile' && (
                <div className="grid md:grid-cols-2 gap-3 ah-profile-grid">
                    <Card title="Contact & details">
                        <dl className="space-y-2 text-sm">
                            {member.dob && (
                                <div>
                                    <dt className="text-slate-400 font-medium">Date of birth</dt>
                                    <dd className="font-semibold">{fmt(member.dob)} {isBirthday(member.dob) && '🎂'}</dd>
                                </div>
                            )}
                            {member.nhs_number && (
                                <div>
                                    <dt className="text-slate-400 font-medium">NHS number</dt>
                                    <dd className="font-semibold">{member.nhs_number}</dd>
                                </div>
                            )}
                            {member.phone && (
                                <div>
                                    <dt className="text-slate-400 font-medium">Phone</dt>
                                    <dd className="font-semibold"><a href={`tel:${member.phone}`} className="text-brand">{member.phone}</a></dd>
                                </div>
                            )}
                            {(member.address_line1 || member.postcode) && (
                                <div>
                                    <dt className="text-slate-400 font-medium">Address</dt>
                                    <dd className="font-semibold">
                                        {[member.address_line1, member.address_line2, member.town, member.postcode].filter(Boolean).join(', ')}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </Card>

                    <Card title="Medical & medication" className="border-l-4 border-l-status-red">
                        <dl className="space-y-2 text-sm">
                            <div>
                                <dt className="text-slate-400 font-medium">Diagnoses</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.diagnoses || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-slate-400 font-medium">Medication</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.medication || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-slate-400 font-medium">Support needs</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.support_needs || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-slate-400 font-medium">Medical notes</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.medical_notes || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-slate-400 font-medium">Interests</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.interests || '—'}</dd>
                            </div>
                        </dl>
                    </Card>

                    <Card title="Staff notes" className="md:col-span-2">
                        {staffNotes.length === 0 && <p className="text-sm text-slate-400">No staff notes recorded.</p>}
                        <ul className="divide-y divide-slate-100 text-sm">
                            {staffNotes.map((note) => (
                                <li key={note.id} className="py-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                                        <span className="font-bold tracking-wide text-brand">{NOTE_TYPE_LABELS[note.note_type] ?? note.note_type}</span>
                                        <span>
                                            {note.noted_at ? fmt(note.noted_at) : 'Date not recorded'}
                                            {note.author_name && ` · ${note.author_name}`}
                                        </span>
                                    </div>
                                    <p className="mt-1 whitespace-pre-wrap text-slate-700">{note.note}</p>
                                </li>
                            ))}
                        </ul>
                    </Card>

                    <Card title="Emergency contacts" className="md:col-span-2">
                        {member.emergency_contacts.length === 0 && <p className="text-sm text-slate-400">None recorded.</p>}
                        <ul className="divide-y divide-slate-100 text-sm">
                            {member.emergency_contacts.map((c, i) => (
                                <li key={i} className="py-2 flex items-center justify-between">
                                    <div>
                                        <div className="font-semibold">{c.name}</div>
                                        {c.relationship && <div className="text-slate-400">{c.relationship}</div>}
                                    </div>
                                    {c.phone && <a href={`tel:${c.phone}`} className="text-brand font-semibold">{c.phone}</a>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            {/* ── Sessions ── */}
            {tab === 'Sessions' && (
                <div className="space-y-3">
                    <Link
                        href={`/members/${member.id}/history`}
                        className="inline-block text-sm font-semibold text-brand"
                    >
                        View full history →
                    </Link>
                    <Card title="End-of-day records">
                        {recentEndOfDay.length === 0 && <p className="text-sm text-slate-400">No records yet.</p>}
                        <ul className="divide-y divide-slate-100">
                            {recentEndOfDay.map((r) => (
                                <li key={r.id} className="py-3">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-sm">
                                            {new Date(r.date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}
                                            {r.concern && <span className="ml-2">⚠️</span>}
                                            {r.incident && <span className="ml-1">🩹</span>}
                                        </span>
                                        <span className="text-lg">
                                            {r.arrival_mood && MOOD_EMOJI[r.arrival_mood]}
                                            {r.end_mood && <> → {MOOD_EMOJI[r.end_mood]}</>}
                                        </span>
                                    </div>
                                    {r.session_type && <div className="text-sm text-slate-500">{r.session_type}</div>}
                                    {r.author && <div className="text-xs text-slate-400">by {r.author}</div>}
                                    {r.activities && <div className="text-sm text-slate-500">{r.activities}</div>}
                                    {r.notes && <div className="text-sm mt-1">{r.notes}</div>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card title="Recent attendance">
                        <ul className="divide-y divide-slate-100 text-sm">
                            {recentAttendance.map((a) => (
                                <li key={a.id} className="py-2 flex items-center justify-between">
                                    <span className="font-medium">
                                        {new Date(a.date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}
                                    </span>
                                    <span>
                                        {a.checked_in ? '✓ Attended' : '—'}
                                        {a.arrival_mood && <span className="ml-1">{MOOD_EMOJI[a.arrival_mood]}</span>}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            {/* ── Comms ── */}
            {tab === '📞 Comms' && (
                <div>
                    <div className="flex gap-2 mb-3">
                        <button onClick={() => setAddingComms(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5">
                            + Log communication
                        </button>
                        <a href={`/email?member=${member.id}`} className="rounded-full bg-brand-dark text-white font-semibold text-sm px-4 py-2.5">
                            ✉️ Compose Email
                        </a>
                    </div>
                    <Card title="Timeline">
                        {commsLog.length === 0 && <p className="text-sm text-slate-400">No communications logged.</p>}
                        <ul className="divide-y divide-slate-100">
                            {commsLog.map((c) => (
                                <li key={c.id} className="py-3 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span className="font-bold text-brand-dark">
                                            {COMMS_ICONS[c.type]} {c.subject || c.type}
                                            <span className="ml-2 text-xs font-semibold text-slate-400 capitalize">{c.direction}</span>
                                        </span>
                                        <span className="text-xs text-slate-400 flex items-center gap-2">
                                            {fmt(c.date)} · {c.user}
                                            <button
                                                onClick={async () => (await confirmDialog('Delete this entry?')) && router.delete(`/members/${member.id}/comms/${c.id}`)}
                                                className="text-red-400 hover:text-red-600 font-bold"
                                                aria-label="Delete entry"
                                            >
                                                ✕
                                            </button>
                                        </span>
                                    </div>
                                    {(c.contact_name || c.organisation) && (
                                        <div className="text-slate-400 text-xs">{[c.contact_name, c.organisation].filter(Boolean).join(' · ')}</div>
                                    )}
                                    {c.summary && <p className="text-slate-600 mt-1 whitespace-pre-wrap">{c.summary}</p>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            {/* ── Goals ── */}
            {tab === 'Goals' && (
                <div>
                    <button onClick={() => setAddingGoal(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + Add goal
                    </button>
                    {goals.length === 0 && <Card><p className="text-sm text-slate-400">No goals set yet.</p></Card>}
                    <div className="space-y-2">
                        {goals.map((g) => (
                            <Card key={g.id} className={g.status === 'achieved' ? 'border-l-4 border-l-status-green' : ''}>
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <div className={`font-bold ${g.status === 'achieved' ? 'text-status-green' : 'text-brand-dark'}`}>
                                            {g.status === 'achieved' && '🏆 '}{g.title}
                                        </div>
                                        {g.description && <p className="text-sm text-slate-500">{g.description}</p>}
                                        <div className="text-xs text-slate-400 mt-1">
                                            {g.target_date && `Target ${fmt(g.target_date)}`}
                                            {g.achieved_at && ` · achieved ${fmt(g.achieved_at)}`}
                                        </div>
                                    </div>
                                    <select
                                        value={g.status}
                                        onChange={(e) => router.put(`/members/${member.id}/goals/${g.id}`, { status: e.target.value })}
                                        className="rounded-lg border border-slate-200 text-xs font-bold !min-h-9 px-2 bg-white capitalize"
                                    >
                                        {['active', 'achieved', 'paused'].map((s) => <option key={s}>{s}</option>)}
                                    </select>
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            )}

            {/* ── Outcomes ── */}
            {tab === 'Outcomes' && (
                <div>
                    <button onClick={() => setAddingOutcome(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + Record outcome
                    </button>
                    <Card title="Outcomes">
                        {outcomes.length === 0 && <p className="text-sm text-slate-400">No outcomes recorded.</p>}
                        <ul className="divide-y divide-slate-100 text-sm">
                            {outcomes.map((o) => (
                                <li key={o.id} className="py-2">
                                    <div className="flex justify-between text-xs text-slate-400">
                                        <span>{fmt(o.date)}{o.goal && ` · 🎯 ${o.goal}`}</span>
                                        <span>{o.user}</span>
                                    </div>
                                    <p className="mt-0.5">{o.outcome}</p>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            {/* ── Alerts ── */}
            {tab === '⚠ Alerts' && (
                <div>
                    <button onClick={() => setAddingAlert(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + Add alert
                    </button>
                    {alerts.length === 0 && <Card><p className="text-sm text-slate-400">No alerts.</p></Card>}
                    <div className="space-y-2">
                        {alerts.map((a) => (
                            <Card key={a.id} className={`border-l-4 ${a.severity === 'red' ? 'border-l-status-red' : 'border-l-status-amber'}`}>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <span className="font-bold capitalize text-brand-dark">{a.type}</span>
                                        <p className="text-sm">{a.text}</p>
                                    </div>
                                    {canEdit && (
                                        <button
                                            onClick={async () => (await confirmDialog('Remove this alert?')) && router.delete(`/members/${member.id}/alerts/${a.id}`)}
                                            className="text-red-400 font-bold"
                                            aria-label="Remove alert"
                                        >
                                            ✕
                                        </button>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            )}

            {/* ── Circle of Care ── */}
            {tab === 'Circle of Care' && (
                <div>
                    <button onClick={() => setAddingContact(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + Add contact
                    </button>
                    {contacts.length === 0 && <Card><p className="text-sm text-slate-400">No contacts in the circle of care yet.</p></Card>}
                    <div className="space-y-2">
                        {contacts.map((c) => (
                            <Card key={c.id}>
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <div className="font-bold text-brand-dark">{c.name}</div>
                                        <div className="text-xs text-slate-400 capitalize">
                                            {[c.role, c.organisation].filter(Boolean).join(' · ')}
                                        </div>
                                        <div className="text-xs mt-1 flex gap-3">
                                            {c.email && <a href={`mailto:${c.email}`} className="text-brand font-semibold">✉️ {c.email}</a>}
                                            {c.phone && <a href={`tel:${c.phone}`} className="text-brand font-semibold">📞 {c.phone}</a>}
                                        </div>
                                    </div>
                                    <button
                                        onClick={async () => (await confirmDialog('Remove this contact?')) && router.delete(`/members/${member.id}/contacts/${c.id}`)}
                                        className="text-red-400 font-bold shrink-0"
                                        aria-label="Remove contact"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            )}

            {/* ── Consents ── */}
            {tab === 'Consents' && (
                <Card title={`Consents (${grantedCount}/${Object.keys(CONSENT_LABELS).length} granted)`}>
                    <ul className="divide-y divide-slate-100">
                        {Object.entries(CONSENT_LABELS).map(([type, label]) => {
                            const c = consents.find((x) => x.consent_type === type);
                            return (
                                <li key={type} className="py-3 flex items-center justify-between text-sm">
                                    <div>
                                        <div className="font-semibold">{label}</div>
                                        {c && <div className="text-xs text-slate-400">Recorded {fmt(c.recorded_on)}{c.notes && ` — ${c.notes}`}</div>}
                                    </div>
                                    <div className="flex gap-1">
                                        {[true, false].map((granted) => (
                                            <button
                                                key={String(granted)}
                                                onClick={() => router.post(`/members/${member.id}/consents`, { consent_type: type, granted })}
                                                className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                                                    c?.granted === granted
                                                        ? granted ? 'bg-status-green text-white' : 'bg-status-red text-white'
                                                        : 'bg-slate-100 text-slate-400'
                                                }`}
                                            >
                                                {granted ? '✓ Granted' : '✕ Declined'}
                                            </button>
                                        ))}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </Card>
            )}

            {/* ── Body Map ── */}
            {tab === 'Body Map' && (
                <div>
                    <button onClick={() => setAddingMap(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + New body map
                    </button>
                    {bodyMaps.length === 0 && <Card><p className="text-sm text-slate-400">No body maps recorded.</p></Card>}
                    <div className="space-y-3">
                        {bodyMaps.map((b) => (
                            <Card key={b.id}>
                                <div className="text-sm font-bold text-brand-dark mb-2">
                                    {new Date(b.recorded_at.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                    <span className="text-xs font-normal text-slate-400"> · {b.user}</span>
                                </div>
                                <div className="flex gap-6">
                                    <BodyMapFigure view="front" markers={b.markers} />
                                    <BodyMapFigure view="back" markers={b.markers} />
                                </div>
                                <ul className="mt-2 text-xs text-slate-500 space-y-0.5">
                                    {b.markers.filter((m) => m.note).map((m, i) => (
                                        <li key={i}>🔴 {m.view}: {m.note}</li>
                                    ))}
                                </ul>
                                {b.notes && <p className="text-sm mt-2">{b.notes}</p>}
                            </Card>
                        ))}
                    </div>
                </div>
            )}

            {/* ── ABC Obs ── */}
            {tab === 'ABC Obs' && (
                <div>
                    <button onClick={() => setAddingAbc(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + Record observation
                    </button>
                    <Card title="ABC observations">
                        {abcObservations.length === 0 && <p className="text-sm text-slate-400">No observations recorded.</p>}
                        <ul className="divide-y divide-slate-100 text-sm">
                            {abcObservations.map((o) => (
                                <li key={o.id} className="py-2">
                                    <div className="flex items-center justify-between">
                                        <b>
                                            {new Date(o.observed_at.replace(' ', 'T')).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                            {o.concern && ' ⚠️'}
                                        </b>
                                        <span className="text-xs text-slate-400">
                                            {o.user}
                                            {o.wellbeing_score && ` · wellbeing ${o.wellbeing_score}/5`}
                                        </span>
                                    </div>
                                    {o.antecedent && <div className="text-slate-500"><b>A:</b> {o.antecedent}</div>}
                                    <div className="text-slate-500"><b>B:</b> {o.behaviour}</div>
                                    {o.consequence && <div className="text-slate-500"><b>C:</b> {o.consequence}</div>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            {/* ── GP Info ── */}
            {tab === 'GP Info' && (
                <Card title="GP details">
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="text-slate-400 font-medium">GP name</dt>
                            <dd className="font-semibold">{member.gp_name || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-400 font-medium">Practice</dt>
                            <dd className="font-semibold">{member.gp_practice || '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-400 font-medium">Phone</dt>
                            <dd className="font-semibold">
                                {member.gp_phone ? <a href={`tel:${member.gp_phone}`} className="text-brand">{member.gp_phone}</a> : '—'}
                            </dd>
                        </div>
                    </dl>
                    {canEdit && (
                        <button onClick={() => setEditing(true)} className="mt-3 text-sm font-bold text-brand">
                            Edit in profile →
                        </button>
                    )}
                </Card>
            )}

            {/* ── Settings ── */}
            {tab === 'Settings' && (
                <Card title="Member settings">
                    <dl className="space-y-3 text-sm">
                        <div className="flex items-center justify-between">
                            <dt className="font-medium">Transport required</dt>
                            <dd>
                                {canEdit ? (
                                    <button
                                        onClick={() => router.put(`/members/${member.id}`, { ...form, transport_required: !member.settings.transport_required })}
                                        className={`w-12 h-7 rounded-full relative transition ${member.settings.transport_required ? 'bg-brand' : 'bg-slate-300'}`}
                                        aria-pressed={member.settings.transport_required}
                                        aria-label="Toggle transport"
                                    >
                                        <span className={`absolute top-1 h-5 w-5 rounded-full bg-white transition-all ${member.settings.transport_required ? 'left-6' : 'left-1'}`} />
                                    </button>
                                ) : (
                                    <span className="font-semibold">{member.settings.transport_required ? 'Yes' : 'No'}</span>
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-400 font-medium">Attendance days</dt>
                            <dd className="font-semibold">
                                {member.settings.attendance_days.map((d) => DAY_LABELS[d]).join(', ') || '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-400 font-medium">Key worker</dt>
                            <dd className="font-semibold">{member.settings.key_worker ?? '—'}</dd>
                        </div>
                    </dl>
                </Card>
            )}

            {/* ══ Modals ══ */}

            <Modal open={editing} title={`Edit — ${member.name}`} onClose={() => setEditing(false)}>
                <div className="grid grid-cols-2 gap-3">
                    {(
                        [
                            ['first_name', 'First name'], ['last_name', 'Last name'], ['preferred_name', 'Preferred name'],
                            ['phone', 'Phone'], ['email', 'Email'], ['nhs_number', 'NHS number'],
                            ['address_line1', 'Address line 1'], ['address_line2', 'Address line 2'], ['town', 'Town'],
                            ['gp_name', 'GP name'], ['gp_practice', 'GP practice'], ['gp_phone', 'GP phone'],
                        ] as const
                    ).map(([key, label]) => (
                        <label key={key} className="block text-sm font-medium">
                            {label}
                            <input
                                value={form[key]}
                                onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                    ))}
                    <label className="block text-sm font-medium relative">
                        Postcode
                        <input
                            value={form.postcode}
                            onChange={(e) => lookupPostcode(e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            autoComplete="off"
                        />
                        {postcodeResults.length > 0 && (
                            <div className="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                {postcodeResults.map((pc) => (
                                    <button
                                        key={pc}
                                        type="button"
                                        onClick={() => pickPostcode(pc)}
                                        className="block w-full text-left px-3 py-2 text-sm hover:bg-slate-50"
                                    >
                                        {pc}
                                    </button>
                                ))}
                            </div>
                        )}
                    </label>
                    <label className="block text-sm font-medium">
                        Date of birth
                        <input type="date" value={form.dob} onChange={(e) => setForm({ ...form, dob: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Status
                        <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            {['active', 'inactive', 'on-leave', 'archived'].map((s) => <option key={s}>{s}</option>)}
                        </select>
                    </label>
                </div>
                <div className="mt-3 space-y-3">
                    {(
                        [['diagnoses', 'Diagnoses'], ['medication', 'Medication'], ['support_needs', 'Support needs']] as const
                    ).map(([key, label]) => (
                        <label key={key} className="block text-sm font-medium">
                            {label}
                            <textarea value={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                        </label>
                    ))}
                    <div>
                        <div className="text-sm font-medium mb-1">Attendance days</div>
                        <div className="flex gap-1.5">
                            {[1, 2, 3, 4, 5].map((d) => (
                                <button
                                    key={d}
                                    type="button"
                                    onClick={() =>
                                        setForm({
                                            ...form,
                                            attendance_days: form.attendance_days.includes(d)
                                                ? form.attendance_days.filter((x) => x !== d)
                                                : [...form.attendance_days, d].sort(),
                                        })
                                    }
                                    className={`h-11 w-12 rounded-lg text-xs font-bold ${
                                        form.attendance_days.includes(d) ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {DAY_LABELS[d].slice(0, 3)}
                                </button>
                            ))}
                        </div>
                    </div>
                    <button onClick={saveProfile} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        Save profile
                    </button>
                </div>
            </Modal>

            <Modal open={addingComms} title="Log communication" onClose={() => setAddingComms(false)}>
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Type
                            <select value={comms.type} onChange={(e) => setComms({ ...comms, type: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white capitalize">
                                {['email', 'phone', 'letter', 'meeting', 'text', 'other'].map((t) => <option key={t}>{t}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Direction
                            <select value={comms.direction} onChange={(e) => setComms({ ...comms, direction: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white capitalize">
                                {['inbound', 'outbound', 'both'].map((d) => <option key={d}>{d}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Contact name
                            <input value={comms.contact_name} onChange={(e) => setComms({ ...comms, contact_name: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Organisation
                            <input value={comms.organisation} onChange={(e) => setComms({ ...comms, organisation: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={comms.date} onChange={(e) => setComms({ ...comms, date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Subject
                            <input value={comms.subject} onChange={(e) => setComms({ ...comms, subject: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Summary
                        <textarea value={comms.summary} onChange={(e) => setComms({ ...comms, summary: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} />
                    </label>
                    <button onClick={() => post(`/members/${member.id}/comms`, comms, () => setAddingComms(false))} className="w-full rounded-lg bg-brand text-white font-bold py-3">
                        Log it
                    </button>
                </div>
            </Modal>

            <Modal open={addingContact} title="Add to circle of care" onClose={() => setAddingContact(false)}>
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Name
                            <input value={contact.name} onChange={(e) => setContact({ ...contact, name: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Role
                            <select value={contact.role} onChange={(e) => setContact({ ...contact, role: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white capitalize">
                                <option value="">—</option>
                                {['social worker', 'appointee', 'gp', 'family', 'advocate', 'other'].map((r) => <option key={r}>{r}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Organisation
                            <input value={contact.organisation} onChange={(e) => setContact({ ...contact, organisation: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Email
                            <input type="email" value={contact.email} onChange={(e) => setContact({ ...contact, email: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Phone
                            <input value={contact.phone} onChange={(e) => setContact({ ...contact, phone: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <button
                        onClick={() => post(`/members/${member.id}/contacts`, contact, () => setAddingContact(false))}
                        disabled={!contact.name}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Add contact
                    </button>
                </div>
            </Modal>

            <Modal open={addingGoal} title="Add goal" onClose={() => setAddingGoal(false)}>
                <div className="space-y-3">
                    <label className="block text-sm font-medium">
                        Goal
                        <input value={goal.title} onChange={(e) => setGoal({ ...goal, title: e.target.value })} placeholder="e.g. Handle the guinea pigs independently" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Description
                        <textarea value={goal.description} onChange={(e) => setGoal({ ...goal, description: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Target date
                        <input type="date" value={goal.target_date} onChange={(e) => setGoal({ ...goal, target_date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <button onClick={() => post(`/members/${member.id}/goals`, goal, () => setAddingGoal(false))} disabled={!goal.title} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add goal
                    </button>
                </div>
            </Modal>

            <Modal open={addingOutcome} title="Record outcome" onClose={() => setAddingOutcome(false)}>
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Date
                            <input type="date" value={outcome.date} onChange={(e) => setOutcome({ ...outcome, date: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Linked goal
                            <select value={outcome.member_goal_id} onChange={(e) => setOutcome({ ...outcome, member_goal_id: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                                <option value="">None</option>
                                {goals.map((g) => <option key={g.id} value={g.id}>{g.title}</option>)}
                            </select>
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        What happened?
                        <textarea value={outcome.outcome} onChange={(e) => setOutcome({ ...outcome, outcome: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} />
                    </label>
                    <button
                        onClick={() => post(`/members/${member.id}/outcomes`, { ...outcome, member_goal_id: outcome.member_goal_id || null }, () => setAddingOutcome(false))}
                        disabled={!outcome.outcome}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Record outcome
                    </button>
                </div>
            </Modal>

            <Modal open={addingAlert} title="Add alert" onClose={() => setAddingAlert(false)}>
                <div className="space-y-3">
                    <label className="block text-sm font-medium">
                        Type
                        <select value={alert.type} onChange={(e) => setAlert({ ...alert, type: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white capitalize">
                            {['allergy', 'medical', 'behaviour', 'dietary', 'other'].map((t) => <option key={t}>{t}</option>)}
                        </select>
                    </label>
                    <label className="block text-sm font-medium">
                        Alert
                        <input value={alert.text} onChange={(e) => setAlert({ ...alert, text: e.target.value })} placeholder="e.g. Nut allergy — EpiPen in office" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <div className="flex gap-2">
                        {(['amber', 'red'] as const).map((s) => (
                            <button
                                key={s}
                                onClick={() => setAlert({ ...alert, severity: s })}
                                className={`flex-1 rounded-lg py-2.5 font-bold capitalize ${
                                    alert.severity === s ? (s === 'red' ? 'bg-status-red text-white' : 'bg-status-amber text-white') : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                    <button onClick={() => post(`/members/${member.id}/alerts`, alert, () => setAddingAlert(false))} disabled={!alert.text} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Add alert
                    </button>
                </div>
            </Modal>

            <Modal open={addingAbc} title="ABC observation" onClose={() => setAddingAbc(false)}>
                <div className="space-y-3">
                    <label className="block text-sm font-medium">
                        When observed
                        <input type="datetime-local" value={abc.observed_at} onChange={(e) => setAbc({ ...abc, observed_at: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        Antecedent — what happened before?
                        <textarea value={abc.antecedent} onChange={(e) => setAbc({ ...abc, antecedent: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Behaviour — what did you observe?
                        <textarea value={abc.behaviour} onChange={(e) => setAbc({ ...abc, behaviour: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <label className="block text-sm font-medium">
                        Consequence — what happened after?
                        <textarea value={abc.consequence} onChange={(e) => setAbc({ ...abc, consequence: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <div className="flex items-center gap-4">
                        <label className="text-sm font-medium">
                            Wellbeing (1–5)
                            <input type="number" min={1} max={5} value={abc.wellbeing_score} onChange={(e) => setAbc({ ...abc, wellbeing_score: e.target.value })} className="mt-1 w-20 rounded-lg border border-slate-300 px-3 block" />
                        </label>
                        <label className="flex items-center gap-2 text-sm font-medium text-red-700 pt-5">
                            <input type="checkbox" checked={abc.concern} onChange={(e) => setAbc({ ...abc, concern: e.target.checked })} className="rounded border-slate-300" />
                            ⚠️ Concern
                        </label>
                    </div>
                    <button
                        onClick={() => post(`/members/${member.id}/abc`, { ...abc, wellbeing_score: abc.wellbeing_score || null }, () => setAddingAbc(false))}
                        disabled={!abc.behaviour}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Save observation
                    </button>
                </div>
            </Modal>

            <Modal open={addingMap} title="Record body map" onClose={() => setAddingMap(false)}>
                <div className="space-y-3">
                    <p className="text-sm text-slate-500">Tap the figure to mark the location of any marks or injuries.</p>
                    <div className="flex gap-2">
                        {(['front', 'back'] as const).map((v) => (
                            <button
                                key={v}
                                onClick={() => setMapView(v)}
                                className={`flex-1 rounded-lg py-2 text-sm font-bold capitalize ${mapView === v ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'}`}
                            >
                                {v}
                            </button>
                        ))}
                    </div>
                    <div className="flex justify-center">
                        <BodyMapFigure
                            view={mapView}
                            markers={newMarkers}
                            onPlace={async (x, y) => {
                                const note = (await promptDialog('Describe this mark (optional):')) ?? '';
                                setNewMarkers([...newMarkers, { view: mapView, x, y, note }]);
                            }}
                        />
                    </div>
                    {newMarkers.length > 0 && (
                        <ul className="text-xs text-slate-500 space-y-0.5">
                            {newMarkers.map((m, i) => (
                                <li key={i} className="flex justify-between">
                                    <span>🔴 {m.view}{m.note && `: ${m.note}`}</span>
                                    <button onClick={() => setNewMarkers(newMarkers.filter((_, j) => j !== i))} className="text-red-500 font-bold">✕</button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <label className="block text-sm font-medium">
                        Notes
                        <textarea value={mapNotes} onChange={(e) => setMapNotes(e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                    <button
                        onClick={() =>
                            post(`/members/${member.id}/body-maps`, { markers: newMarkers.map((m) => ({ ...m })), notes: mapNotes }, () => {
                                setAddingMap(false);
                                setNewMarkers([]);
                                setMapNotes('');
                            })
                        }
                        disabled={newMarkers.length === 0}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Save body map ({newMarkers.length} marker{newMarkers.length === 1 ? '' : 's'})
                    </button>
                </div>
            </Modal>
        </AppShell>
    );
}
