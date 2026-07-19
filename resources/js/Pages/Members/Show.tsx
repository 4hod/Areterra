import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
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
    canEdit: boolean;
}

const TABS = ['Profile', 'Sessions', 'Settings'] as const;

export default function Show({ member, recentAttendance, recentEndOfDay, canEdit }: Props) {
    const [tab, setTab] = useState<(typeof TABS)[number]>('Profile');

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
