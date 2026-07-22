import { Head } from '@inertiajs/react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';

interface Submission {
    id: number;
    submitted_by: string;
    submitted_at: string;
    answers: Record<number, string>;
}

interface Props {
    form: { title: string; fields: { id: number; label: string }[] };
    submissions: Submission[];
}

export default function Submissions({ form, submissions }: Props) {
    return (
        <AppShell title={`${form.title} — responses`}>
            <Head title={`${form.title} — responses`} />

            <div className="space-y-2">
                {submissions.map((s) => (
                    <Card key={s.id}>
                        <div className="text-xs text-slate-400 mb-2">
                            {s.submitted_by} · {new Date(s.submitted_at).toLocaleString('en-GB')}
                        </div>
                        <dl className="space-y-1">
                            {form.fields.map((f) => (
                                <div key={f.id} className="text-sm">
                                    <dt className="inline font-semibold text-brand-dark">{f.label}: </dt>
                                    <dd className="inline text-slate-600">{s.answers[f.id] || '—'}</dd>
                                </div>
                            ))}
                        </dl>
                    </Card>
                ))}
                {submissions.length === 0 && <Card><p className="text-slate-500">No responses yet.</p></Card>}
            </div>
        </AppShell>
    );
}
