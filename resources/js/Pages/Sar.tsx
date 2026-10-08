import { Head, usePage } from '@inertiajs/react';
import { MOOD_EMOJI, Mood, SharedProps } from '../types';
import ModuleHero from '../components/ModuleHero';

// Subject Access Request extract — print-friendly full data record for one member.
interface Props {
    generated_at: string;
    member: Record<string, any>;
    attendance: { date: string; checked_in: boolean; arrival_mood: Mood | null; notes: string | null }[];
    endOfDay: { date: string; arrival_mood: Mood | null; end_mood: Mood | null; session_type: string | null; activities: string | null; notes: string | null; concern: boolean; food_intake?: string | null; fluid_intake?: string | null; toileting_notes?: string | null; medication_given?: boolean; medication_notes?: string | null; concern_detail?: string | null; incident?: boolean; incident_detail?: string | null; photos?: string[] }[];
    reviews: { date: string; outcomes: string | null; actions: string | null }[];
    abc: { date: string; antecedent: string | null; behaviour: string; consequence: string | null; wellbeing_score: number | null }[];
    transportLedger: { date: string; type: string; amount: number }[];
    contacts: { name: string; role: string | null; organisation: string | null; email: string | null; phone: string | null; notes: string | null }[];
    consents: { type: string; granted: boolean; recorded_on: string | null; expires_at: string | null; notes: string | null }[];
    communications: { date: string | null; type: string; direction: string; subject: string | null; summary: string | null; contact_name: string | null; organisation: string | null; recorded_by: string | null }[];
    goals: { title: string; description: string | null; status: string; target_date: string | null; achieved_at: string | null }[];
    outcomes: { date: string | null; outcome: string; goal: string | null; recorded_by: string | null }[];
    alerts: { type: string; severity: string; text: string }[];
    bodyMaps: { recorded_at: string | null; markers: { view: string; x: number; y: number; note?: string }[]; notes: string | null; recorded_by: string | null }[];
    additionalSections: Record<string, Record<string, unknown>[]>;
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="mb-6 break-inside-avoid">
            <h2 className="font-extrabold border-b-2 pb-1 mb-2" style={{ color: '#00345C', borderColor: '#009DE6' }}>
                {title}
            </h2>
            {children}
        </section>
    );
}

export default function Sar({ generated_at, member, attendance, endOfDay, reviews, abc, transportLedger, contacts, consents, communications, goals, outcomes, alerts, bodyMaps, additionalSections }: Props) {
    const { branding } = usePage<SharedProps>().props;

    return (
        <div className="bg-white min-h-screen text-black p-8 max-w-3xl mx-auto text-sm">
            <Head title={`SAR — ${member.name}`}>
                <style>{`@media print { .no-print { display: none } } @page { margin: 15mm }`}</style>
            </Head>
            <ModuleHero eyebrow="Information rights" title="Subject access requests" description="Track requests, deadlines and disclosure work in one secure place." icon="🔐" tone="slate" />

            <div className="flex items-start justify-between mb-6">
                <div>
                    {branding.logoUrl ? (
                        <img src={branding.logoUrl} alt={branding.orgName} className="h-10 mb-1 object-contain object-left" />
                    ) : (
                        <div className="text-2xl font-extrabold" style={{ color: '#00345C' }}>{branding.orgName}</div>
                    )}
                    <div className="text-xs text-slate-500">Subject Access Request — data extract</div>
                </div>
                <button onClick={() => window.print()} className="no-print rounded bg-slate-800 text-white text-sm font-semibold px-4 py-2">
                    🖨 Print
                </button>
            </div>

            <h1 className="text-xl font-extrabold mb-1">{member.name}</h1>
            <p className="text-xs text-slate-500 mb-6">Generated {generated_at}. Contains all personal data held in the Areterra Hub.</p>

            <Section title="Personal details">
                <table className="w-full">
                    <tbody>
                        {Object.entries({
                            'Preferred name': member.preferred_name,
                            Status: member.status,
                            'Date of birth': member.dob,
                            Gender: member.gender,
                            'NHS number': member.nhs_number,
                            Phone: member.phone,
                            Email: member.email,
                            Address: member.address,
                            'Key worker': member.key_worker,
                            'Support needs': member.support_needs,
                            'Medical notes': member.medical_notes,
                            Interests: member.interests,
                            Diagnoses: member.diagnoses,
                            Medication: member.medication,
                            Allergies: member.allergies,
                            GP: [member.gp_name, member.gp_practice, member.gp_phone].filter(Boolean).join(' · '),
                        })
                            .filter(([, v]) => v)
                            .map(([k, v]) => (
                                <tr key={k} className="border-b border-slate-100">
                                    <td className="py-1 pr-4 font-semibold w-40">{k}</td>
                                    <td className="py-1">{String(v)}</td>
                                </tr>
                            ))}
                    </tbody>
                </table>
                {member.emergency_contacts?.length > 0 && (
                    <p className="mt-2">
                        <b>Emergency contacts:</b>{' '}
                        {member.emergency_contacts.map((c: any) => `${c.name}${c.relationship ? ` (${c.relationship})` : ''}${c.phone ? ` ${c.phone}` : ''}`).join('; ')}
                    </p>
                )}
            </Section>

            <Section title={`Circle of care (${contacts.length})`}>
                {contacts.map((contact, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{contact.name}</b>
                        {[contact.role, contact.organisation].filter(Boolean).length > 0 && ` — ${[contact.role, contact.organisation].filter(Boolean).join(' · ')}`}
                        {[contact.phone, contact.email].filter(Boolean).length > 0 && <div>{[contact.phone, contact.email].filter(Boolean).join(' · ')}</div>}
                        {contact.notes && <div>Notes: {contact.notes}</div>}
                    </div>
                ))}
                {contacts.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Alerts (${alerts.length})`}>
                {alerts.map((alert, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{alert.severity.toUpperCase()} · {alert.type}</b> — {alert.text}
                    </div>
                ))}
                {alerts.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Consents (${consents.length})`}>
                {consents.map((consent, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{consent.type.replace(/_/g, ' ')}</b> — {consent.granted ? 'granted' : 'declined'}
                        {consent.recorded_on && ` on ${new Date(consent.recorded_on).toLocaleDateString('en-GB')}`}
                        {consent.expires_at && ` · review by ${new Date(consent.expires_at).toLocaleDateString('en-GB')}`}
                        {consent.notes && <div>Notes: {consent.notes}</div>}
                    </div>
                ))}
                {consents.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Goals (${goals.length})`}>
                {goals.map((goal, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{goal.title}</b> — {goal.status}
                        {goal.target_date && ` · target ${new Date(goal.target_date).toLocaleDateString('en-GB')}`}
                        {goal.description && <div>{goal.description}</div>}
                    </div>
                ))}
                {goals.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Outcomes (${outcomes.length})`}>
                {outcomes.map((outcome, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{outcome.date ? new Date(outcome.date).toLocaleDateString('en-GB') : 'Undated'}</b>
                        {outcome.goal && ` · ${outcome.goal}`}
                        <div>{outcome.outcome}</div>
                        {outcome.recorded_by && <div className="text-slate-500">Recorded by {outcome.recorded_by}</div>}
                    </div>
                ))}
                {outcomes.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Communications (${communications.length})`}>
                {communications.map((communication, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{communication.date ? new Date(communication.date).toLocaleDateString('en-GB') : 'Undated'} · {communication.type} · {communication.direction}</b>
                        {communication.subject && ` — ${communication.subject}`}
                        {communication.summary && <div>{communication.summary}</div>}
                        {[communication.contact_name, communication.organisation].filter(Boolean).length > 0 && <div>Contact: {[communication.contact_name, communication.organisation].filter(Boolean).join(' · ')}</div>}
                        {communication.recorded_by && <div className="text-slate-500">Recorded by {communication.recorded_by}</div>}
                    </div>
                ))}
                {communications.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Attendance (${attendance.length} records)`}>
                {attendance.map((a, i) => (
                    <div key={i} className="py-0.5 border-b border-slate-50">
                        {new Date(a.date).toLocaleDateString('en-GB')} — {a.checked_in ? 'attended' : 'not checked in'}
                        {a.arrival_mood && ` ${MOOD_EMOJI[a.arrival_mood]}`}
                        {a.notes && ` — ${a.notes}`}
                    </div>
                ))}
                {attendance.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Session records (${endOfDay.length})`}>
                {endOfDay.map((r, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{new Date(r.date).toLocaleDateString('en-GB')}</b>
                        {r.session_type && ` · ${r.session_type}`}
                        {r.arrival_mood && ` · arrived ${MOOD_EMOJI[r.arrival_mood]}`}
                        {r.end_mood && ` → ${MOOD_EMOJI[r.end_mood]}`}
                        {r.concern && ' · ⚠ concern flagged'}
                        {r.activities && <div>Activities: {r.activities}</div>}
                        {r.food_intake && <div>Food intake: {r.food_intake}</div>}
                        {r.fluid_intake && <div>Fluid intake: {r.fluid_intake}</div>}
                        {r.toileting_notes && <div>Toileting: {r.toileting_notes}</div>}
                        {r.medication_given && <div>Medication given{r.medication_notes ? ` — ${r.medication_notes}` : ''}</div>}
                        {r.concern_detail && <div>Concern detail: {r.concern_detail}</div>}
                        {r.incident_detail && <div>Incident detail: {r.incident_detail}</div>}
                        {r.notes && <div>Notes: {r.notes}</div>}
                        {r.photos && r.photos.length > 0 && <div className="mt-2 grid grid-cols-2 gap-2">{r.photos.map((photo) => <img key={photo} src={photo} alt={`Session record ${r.date}`} className="max-h-48 object-contain" />)}</div>}
                    </div>
                ))}
                {endOfDay.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Reviews (${reviews.length})`}>
                {reviews.map((r, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{new Date(r.date).toLocaleDateString('en-GB')}</b>
                        {r.outcomes && <div>Outcomes: {r.outcomes}</div>}
                        {r.actions && <div>Actions: {r.actions}</div>}
                    </div>
                ))}
                {reviews.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`ABC observations (${abc.length})`}>
                {abc.map((o, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{new Date(o.date.replace(' ', 'T')).toLocaleDateString('en-GB')}</b>
                        {o.antecedent && <div>A: {o.antecedent}</div>}
                        <div>B: {o.behaviour}</div>
                        {o.consequence && <div>C: {o.consequence}</div>}
                        {o.wellbeing_score && <div>Wellbeing: {o.wellbeing_score}/5</div>}
                    </div>
                ))}
                {abc.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Body maps (${bodyMaps.length})`}>
                {bodyMaps.map((bodyMap, i) => (
                    <div key={i} className="py-1 border-b border-slate-50">
                        <b>{bodyMap.recorded_at ? new Date(bodyMap.recorded_at.replace(' ', 'T')).toLocaleString('en-GB') : 'Undated'}</b>
                        <div>{bodyMap.markers.length} marker{bodyMap.markers.length === 1 ? '' : 's'}</div>
                        {bodyMap.markers.map((marker, markerIndex) => (
                            <div key={markerIndex}>{marker.view} ({marker.x}%, {marker.y}%){marker.note ? ` — ${marker.note}` : ''}</div>
                        ))}
                        {bodyMap.notes && <div>Notes: {bodyMap.notes}</div>}
                        {bodyMap.recorded_by && <div className="text-slate-500">Recorded by {bodyMap.recorded_by}</div>}
                    </div>
                ))}
                {bodyMaps.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <Section title={`Transport ledger (${transportLedger.length} entries)`}>
                {transportLedger.map((t, i) => (
                    <div key={i} className="py-0.5 border-b border-slate-50">
                        {new Date(t.date).toLocaleDateString('en-GB')} — {t.type} £{t.amount.toFixed(2)}
                    </div>
                ))}
                {transportLedger.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            {Object.entries(additionalSections).map(([title, records]) => (
                <Section key={title} title={`${title} (${records.length})`}>
                    {records.map((record, index) => (
                        <div key={index} className="mb-2 border-b border-slate-100 pb-2">
                            {Object.entries(record).filter(([, value]) => value !== null && value !== '' && value !== undefined).map(([label, value]) => (
                                <div key={label}><b>{label.replace(/_/g, ' ')}:</b>{' '}{typeof value === 'object' ? JSON.stringify(value) : String(value)}</div>
                            ))}
                        </div>
                    ))}
                    {records.length === 0 && <p className="text-slate-400">None.</p>}
                </Section>
            ))}

            <p className="text-[10px] text-slate-400 mt-8">
                Areterra · Little Croft, Fenn Green, WV15 6JA · 01562 307 306 · Registered charity No. 1196211
            </p>
        </div>
    );
}
