import { Head, usePage } from '@inertiajs/react';
import { MOOD_EMOJI, Mood, SharedProps } from '../types';
import ModuleHero from '../components/ModuleHero';

// Subject Access Request extract — print-friendly full data record for one member.
interface Props {
    generated_at: string;
    member: Record<string, any>;
    attendance: { date: string; checked_in: boolean; arrival_mood: Mood | null; notes: string | null }[];
    endOfDay: { date: string; arrival_mood: Mood | null; end_mood: Mood | null; session_type: string | null; activities: string | null; notes: string | null; concern: boolean }[];
    reviews: { date: string; outcomes: string | null; actions: string | null }[];
    abc: { date: string; antecedent: string | null; behaviour: string; consequence: string | null; wellbeing_score: number | null }[];
    transportLedger: { date: string; type: string; amount: number }[];
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

export default function Sar({ generated_at, member, attendance, endOfDay, reviews, abc, transportLedger }: Props) {
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
                            'NHS number': member.nhs_number,
                            Phone: member.phone,
                            Email: member.email,
                            Address: member.address,
                            'Key worker': member.key_worker,
                            'Support needs': member.support_needs,
                            Diagnoses: member.diagnoses,
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
                        {r.notes && <div>Notes: {r.notes}</div>}
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

            <Section title={`Transport ledger (${transportLedger.length} entries)`}>
                {transportLedger.map((t, i) => (
                    <div key={i} className="py-0.5 border-b border-slate-50">
                        {new Date(t.date).toLocaleDateString('en-GB')} — {t.type} £{t.amount.toFixed(2)}
                    </div>
                ))}
                {transportLedger.length === 0 && <p className="text-slate-400">None.</p>}
            </Section>

            <p className="text-[10px] text-slate-400 mt-8">
                Areterra · Little Croft, Fenn Green, WV15 6JA · 01562 307 306 · Registered charity No. 1196211
            </p>
        </div>
    );
}
