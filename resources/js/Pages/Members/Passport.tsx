import { Head, Link } from '@inertiajs/react';
import AppShell from '../../components/AppShell';
import { MOOD_EMOJI, Mood } from '../../types';

interface Props {
    member: {
        id: number; name: string; preferred_name: string | null; photo_path: string | null;
        support_needs: string | null; interests: string | null; allergies: string | null;
        medication: string | null; medical_notes: string | null; key_worker: string | null;
        transport_required: boolean;
        emergency_contacts: { name: string; relationship?: string; phone?: string }[];
    };
    today: { status: string; checked_in: boolean; arrival_mood: Mood | null; notes: string | null };
    alerts: { id: number; type: string; text: string; severity: string }[];
    goals: { id: number; title: string; description: string | null }[];
    recentHandover: { date: string; end_mood: Mood | null; notes: string | null; concern: boolean; concern_detail: string | null } | null;
}

function Section({ icon, title, value, urgent = false }: { icon: string; title: string; value: string | null; urgent?: boolean }) {
    return (
        <section className={`passport-section ${urgent ? 'is-urgent' : ''}`}>
            <div className="passport-section-icon">{icon}</div>
            <div><h2>{title}</h2><p>{value || 'Nothing recorded.'}</p></div>
        </section>
    );
}

export default function Passport({ member, today, alerts, goals, recentHandover }: Props) {
    return (
        <AppShell title={`${member.preferred_name || member.name} · Day Passport`}>
            <Head title={`${member.name} — Day Passport`} />
            <div className="passport-page">
                <div className="passport-toolbar">
                    <Link href={`/members/${member.id}`}>← Full profile</Link>
                    <button onClick={() => window.print()}>🖨 Print passport</button>
                </div>

                <header className="passport-hero">
                    {member.photo_path ? <img src={member.photo_path} alt="" /> : <div className="passport-avatar">{member.name[0]}</div>}
                    <div>
                        <span>MY ARETERRA DAY</span>
                        <h1>{member.preferred_name || member.name}</h1>
                        <p>{today.checked_in ? '✓ Here today' : today.status === 'absent' ? 'Absent today' : 'Expected today'}
                            {today.arrival_mood && <> · Arrived feeling {MOOD_EMOJI[today.arrival_mood]}</>}
                        </p>
                    </div>
                </header>

                {alerts.length > 0 && <div className="passport-alerts">
                    {alerts.map((alert) => <div key={alert.id} className={alert.severity === 'red' ? 'red' : 'amber'}>
                        <b>⚠ {alert.type}</b><span>{alert.text}</span>
                    </div>)}
                </div>}

                <div className="passport-grid">
                    <Section icon="🤝" title="How best to support me" value={member.support_needs} />
                    <Section icon="💛" title="What matters to me" value={member.interests} />
                    <Section icon="⚕" title="Allergies" value={member.allergies || 'No known allergies recorded.'} urgent={Boolean(member.allergies && !/none|no known/i.test(member.allergies))} />
                    <Section icon="💊" title="Medication" value={member.medication} />
                    <Section icon="🩺" title="Important health information" value={member.medical_notes} />
                    <Section icon="🚐" title="Today’s practical plan" value={`${member.transport_required ? 'Transport required.' : 'No transport required.'}${member.key_worker ? ` Key worker: ${member.key_worker}.` : ''}`} />
                </div>

                {goals.length > 0 && <section className="passport-wide-card">
                    <h2>🎯 What we are working towards</h2>
                    <ul>{goals.map((goal) => <li key={goal.id}><b>{goal.title}</b>{goal.description && <span>{goal.description}</span>}</li>)}</ul>
                </section>}

                {recentHandover && <section className={`passport-wide-card ${recentHandover.concern ? 'has-concern' : ''}`}>
                    <h2>↪ Latest handover</h2>
                    <p className="passport-meta">{new Date(recentHandover.date).toLocaleDateString('en-GB')}{recentHandover.end_mood && ` · ${MOOD_EMOJI[recentHandover.end_mood]}`}</p>
                    <p>{recentHandover.concern_detail || recentHandover.notes || 'No handover notes recorded.'}</p>
                </section>}

                <section className="passport-wide-card">
                    <h2>☎ Emergency contacts</h2>
                    {member.emergency_contacts.length === 0 ? <p>None recorded.</p> : <ul>{member.emergency_contacts.map((contact, i) =>
                        <li key={i}><b>{contact.name}</b><span>{contact.relationship || 'Emergency contact'}{contact.phone && <> · <a href={`tel:${contact.phone}`}>{contact.phone}</a></>}</span></li>
                    )}</ul>}
                </section>

                <p className="passport-footnote">A quick working view for today. The full member record remains the source of truth.</p>
            </div>
        </AppShell>
    );
}
