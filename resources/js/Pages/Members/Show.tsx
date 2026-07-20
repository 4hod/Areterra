import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../components/AppShell';
import BodyMapFigure from '../../components/BodyMapFigure';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import { MOOD_EMOJI, Mood } from '../../types';

const DAY_LABELS: Record<number, string> = { 1: 'Monday', 2: 'Tuesday', 3: 'Wednesday', 4: 'Thursday', 5: 'Friday', 6: 'Saturday', 7: 'Sunday' };

interface EmergencyContact {
    name: string;
    relationship?: string;
    phone?: string;
}

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
        diagnoses: string | null;
        emergency_contacts: EmergencyContact[];
        phone: string | null;
        email: string | null;
        address_line1: string | null;
        address_line2: string | null;
        town: string | null;
        postcode: string | null;
        settings: {
            transport_required: boolean;
            attendance_days: number[];
            key_worker: string | null;
        };
    };
    recentAttendance: { id: number; date: string; checked_in: boolean; arrival_mood: Mood | null; notes: string | null }[];
    recentEndOfDay: {
        id: number;
        date: string;
        arrival_mood: Mood | null;
        end_mood: Mood | null;
        session_type: string | null;
        activities: string | null;
        notes: string | null;
        concern: boolean;
    }[];
    abcObservations: {
        id: number;
        observed_at: string;
        antecedent: string | null;
        behaviour: string;
        consequence: string | null;
        wellbeing_score: number | null;
        concern: boolean;
        user: string;
    }[];
    bodyMaps: {
        id: number;
        recorded_at: string;
        markers: { view: 'front' | 'back'; x: number; y: number; note?: string | null }[];
        notes: string | null;
        user: string;
    }[];
    canEdit: boolean;
}

const TABS = ['Profile', 'Sessions', 'ABC Obs', 'Body Map', 'Settings'] as const;

export default function Show({ member, recentAttendance, recentEndOfDay, abcObservations, bodyMaps, canEdit }: Props) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Profile');
    const [addingAbc, setAddingAbc] = useState(false);
    const [abc, setAbc] = useState({
        observed_at: new Date().toISOString().slice(0, 16),
        antecedent: '',
        behaviour: '',
        consequence: '',
        wellbeing_score: '' as string | number,
        concern: false,
    });
    const [addingMap, setAddingMap] = useState(false);
    const [mapView, setMapView] = useState<'front' | 'back'>('front');
    const [newMarkers, setNewMarkers] = useState<{ view: 'front' | 'back'; x: number; y: number; note: string }[]>([]);
    const [mapNotes, setMapNotes] = useState('');

    function saveAbc() {
        router.post(`/members/${member.id}/abc`, { ...abc, wellbeing_score: abc.wellbeing_score || null }, {
            onSuccess: () => setAddingAbc(false),
        });
    }

    function saveBodyMap() {
        router.post(
            `/members/${member.id}/body-maps`,
            { markers: newMarkers.map((m) => ({ ...m })), notes: mapNotes },
            {
                onSuccess: () => {
                    setAddingMap(false);
                    setNewMarkers([]);
                    setMapNotes('');
                },
            },
        );
    }

    const address = [member.address_line1, member.address_line2, member.town, member.postcode]
        .filter(Boolean)
        .join(', ');

    return (
        <AppShell title={member.name}>
            <Head title={member.name} />

            <div className="flex items-center gap-4 mb-4">
                <div className="h-16 w-16 rounded-full bg-brand/10 text-brand font-extrabold text-2xl flex items-center justify-center">
                    {member.name.charAt(0)}
                </div>
                <div>
                    <div className="text-xl font-extrabold text-brand-dark">{member.name}</div>
                    <StatusPill status={member.status} />
                </div>
                {canEdit && (
                    <a
                        href={`/members/${member.id}/sar`}
                        target="_blank"
                        className="ml-auto rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2"
                    >
                        📄 SAR export
                    </a>
                )}
            </div>

            <div className="flex gap-1 mb-4 overflow-x-auto">
                {TABS.map((t) => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap ${
                            tab === t ? 'bg-brand text-white' : 'bg-white text-slate-600 border border-slate-200'
                        }`}
                    >
                        {t}
                    </button>
                ))}
            </div>

            {tab === 'Profile' && (
                <div className="grid md:grid-cols-2 gap-3">
                    <Card title="Details">
                        <dl className="space-y-2 text-sm">
                            {member.dob && (
                                <div>
                                    <dt className="text-slate-400 font-medium">Date of birth</dt>
                                    <dd className="font-semibold">{new Date(member.dob).toLocaleDateString('en-GB')}</dd>
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
                                    <dd className="font-semibold">{member.phone}</dd>
                                </div>
                            )}
                            {address && (
                                <div>
                                    <dt className="text-slate-400 font-medium">Address</dt>
                                    <dd className="font-semibold">{address}</dd>
                                </div>
                            )}
                        </dl>
                    </Card>

                    <Card title="Support">
                        <dl className="space-y-2 text-sm">
                            <div>
                                <dt className="text-slate-400 font-medium">Support needs</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.support_needs || '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-slate-400 font-medium">Diagnoses</dt>
                                <dd className="font-medium whitespace-pre-wrap">{member.diagnoses || '—'}</dd>
                            </div>
                        </dl>
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
                                    {c.phone && (
                                        <a href={`tel:${c.phone}`} className="text-brand font-semibold">
                                            {c.phone}
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            )}

            {tab === 'Sessions' && (
                <div className="space-y-3">
                    <Card title="Recent end-of-day records">
                        {recentEndOfDay.length === 0 && <p className="text-sm text-slate-400">No records yet.</p>}
                        <ul className="divide-y divide-slate-100">
                            {recentEndOfDay.map((r) => (
                                <li key={r.id} className="py-3">
                                    <div className="flex items-center justify-between">
                                        <span className="font-semibold text-sm">
                                            {new Date(r.date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}
                                            {r.concern && <span className="ml-2">⚠️</span>}
                                        </span>
                                        <span className="text-lg">
                                            {r.arrival_mood && MOOD_EMOJI[r.arrival_mood]}
                                            {r.end_mood && <> → {MOOD_EMOJI[r.end_mood]}</>}
                                        </span>
                                    </div>
                                    {r.session_type && <div className="text-sm text-slate-500">{r.session_type}</div>}
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

            {tab === 'Body Map' && (
                <div>
                    <button onClick={() => setAddingMap(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-4 py-2.5 mb-3">
                        + New body map
                    </button>
                    {bodyMaps.length === 0 && (
                        <Card><p className="text-sm text-slate-400">No body maps recorded.</p></Card>
                    )}
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

            {/* ABC modal */}
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
                        <textarea value={abc.behaviour} onChange={(e) => setAbc({ ...abc, behaviour: e.target.value })} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} required />
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
                    <button onClick={saveAbc} disabled={!abc.behaviour} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Save observation
                    </button>
                </div>
            </Modal>

            {/* Body map modal */}
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
                            onPlace={(x, y) => {
                                const note = prompt('Describe this mark (optional):') ?? '';
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
                    <button onClick={saveBodyMap} disabled={newMarkers.length === 0} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Save body map ({newMarkers.length} marker{newMarkers.length === 1 ? '' : 's'})
                    </button>
                </div>
            </Modal>

            {tab === 'Settings' && (
                <Card title="Member settings">
                    <dl className="space-y-3 text-sm">
                        <div className="flex items-center justify-between">
                            <dt className="font-medium">Transport required</dt>
                            <dd>
                                {canEdit ? (
                                    <button
                                        onClick={() =>
                                            router.put(`/members/${member.id}`, {
                                                first_name: member.first_name,
                                                last_name: member.last_name,
                                                preferred_name: member.preferred_name,
                                                status: member.status,
                                                transport_required: !member.settings.transport_required,
                                                attendance_days: member.settings.attendance_days,
                                            })
                                        }
                                        className={`w-12 h-7 rounded-full relative transition ${
                                            member.settings.transport_required ? 'bg-brand' : 'bg-slate-300'
                                        }`}
                                        aria-pressed={member.settings.transport_required}
                                        aria-label="Toggle transport"
                                    >
                                        <span
                                            className={`absolute top-1 h-5 w-5 rounded-full bg-white transition-all ${
                                                member.settings.transport_required ? 'left-6' : 'left-1'
                                            }`}
                                        />
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
        </AppShell>
    );
}
