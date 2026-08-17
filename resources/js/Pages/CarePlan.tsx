import { Head, usePage } from '@inertiajs/react';
import { SharedProps } from '../types';

interface EmergencyContact {
    name?: string;
    relationship?: string;
    phone?: string;
}

interface Props {
    generated_at: string;
    member: {
        name: string;
        dob: string | null;
        photo_path: string | null;
        support_needs: string | null;
        diagnoses: string | null;
        medication: string | null;
        emergency_contacts: EmergencyContact[];
        key_worker: string | null;
        transport_required: boolean;
        typical_week: string[];
    };
    goals: { title: string; description: string | null }[];
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="mb-5 break-inside-avoid">
            <h2 className="font-extrabold border-b-2 pb-1 mb-2" style={{ color: '#00345C', borderColor: '#009DE6' }}>
                {title}
            </h2>
            {children}
        </section>
    );
}

export default function CarePlan({ generated_at, member, goals }: Props) {
    const { branding } = usePage<SharedProps>().props;

    return (
        <div className="bg-white min-h-screen text-black p-8 max-w-3xl mx-auto text-sm">
            <Head title={`Care Plan — ${member.name}`}>
                <style>{`@media print { .no-print { display: none } } @page { margin: 15mm }`}</style>
            </Head>

            <div className="flex items-start justify-between mb-6">
                <div>
                    {branding.logoUrl ? (
                        <img src={branding.logoUrl} alt={branding.orgName} className="h-10 mb-1 object-contain object-left" />
                    ) : (
                        <div className="text-2xl font-extrabold" style={{ color: '#00345C' }}>{branding.orgName}</div>
                    )}
                    <div className="text-xs text-slate-500">Individual care plan &amp; weekly timetable</div>
                </div>
                <button onClick={() => window.print()} className="no-print rounded bg-slate-800 text-white text-sm font-semibold px-4 py-2">
                    🖨 Print
                </button>
            </div>

            <div className="flex items-center gap-4 mb-6">
                {member.photo_path && <img src={member.photo_path} alt={member.name} className="h-20 w-20 rounded-full object-cover" />}
                <div>
                    <div className="text-2xl font-extrabold" style={{ color: '#00345C' }}>{member.name}</div>
                    <div className="text-slate-500">
                        {member.dob && `Born ${member.dob}`}
                        {member.key_worker && ` · Key worker: ${member.key_worker}`}
                    </div>
                </div>
            </div>

            <Section title="Typical week">
                {member.typical_week.length === 0 ? (
                    <p className="text-slate-400">No regular attendance days set.</p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {member.typical_week.map((day) => (
                            <span key={day} className="rounded-full border-2 px-3 py-1 font-semibold" style={{ borderColor: '#009DE6', color: '#00345C' }}>
                                {day}
                            </span>
                        ))}
                    </div>
                )}
                {member.transport_required && <p className="mt-2 text-slate-600">🚐 Transport arranged</p>}
            </Section>

            {(member.support_needs || member.diagnoses) && (
                <Section title="Support needs">
                    {member.support_needs && <p className="mb-2 whitespace-pre-wrap">{member.support_needs}</p>}
                    {member.diagnoses && <p className="text-slate-600 whitespace-pre-wrap">Diagnoses: {member.diagnoses}</p>}
                </Section>
            )}

            {member.medication && (
                <Section title="Medication">
                    <p className="whitespace-pre-wrap">{member.medication}</p>
                </Section>
            )}

            {goals.length > 0 && (
                <Section title="Current goals">
                    <ul className="list-disc pl-5 space-y-1">
                        {goals.map((g, i) => (
                            <li key={i}>
                                <b>{g.title}</b>
                                {g.description && ` — ${g.description}`}
                            </li>
                        ))}
                    </ul>
                </Section>
            )}

            <Section title="Emergency contacts">
                {member.emergency_contacts.length === 0 ? (
                    <p className="text-slate-400">None recorded.</p>
                ) : (
                    <table className="w-full text-left">
                        <tbody>
                            {member.emergency_contacts.map((c, i) => (
                                <tr key={i} className="border-b border-slate-100">
                                    <td className="py-1 pr-3 font-semibold">{c.name}</td>
                                    <td className="py-1 pr-3 text-slate-500">{c.relationship}</td>
                                    <td className="py-1">{c.phone}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Section>

            <p className="text-[10px] text-slate-400 mt-6 text-center">
                {branding.orgName} · Registered charity No. 1196211 · Generated {generated_at}
            </p>
        </div>
    );
}
