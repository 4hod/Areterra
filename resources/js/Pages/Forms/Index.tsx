import { Head, Link, router } from '@inertiajs/react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import EmptyState from '../../components/EmptyState';
import ModuleHero from '../../components/ModuleHero';

interface FormRow {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    submissions_count: number;
}

interface LinkedSubmission { id: number; form: string; submitted_by: string; submitted_at: string }

export default function Index({ forms, canBuild, context, linkedSubmissions }: { forms: FormRow[]; canBuild: boolean; context: { id: number; type: string; name: string; url: string | null } | null; linkedSubmissions: LinkedSubmission[] }) {
    const subjectQuery = context ? `?about=${context.type}&id=${context.id}` : '';
    return (
        <AppShell title="Forms">
            <Head title="Forms" />
            <ModuleHero eyebrow="Digital records" title="Forms" description="Create, manage and launch the forms your team needs." icon="📝" tone="purple" />

            {context && <div className="mb-4 flex items-center justify-between rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm"><span>New submissions will be linked to <b>{context.name}</b></span><Link href="/forms" className="font-bold text-brand">Clear link</Link></div>}

            {canBuild && (
                <Link href="/forms/new" className="inline-block rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + New form
                </Link>
            )}

            <div className="space-y-2">
                {forms.map((f) => (
                    <Card key={f.id} className={!f.is_active ? 'opacity-60' : ''}>
                        <div className="flex items-center justify-between gap-2">
                            <div className="min-w-0">
                                <Link href={`/forms/${f.slug}${subjectQuery}`} className="font-bold text-brand-dark hover:underline">
                                    {f.title}{!f.is_active && ' (inactive)'}
                                </Link>
                                {f.description && <p className="text-sm text-slate-500">{f.description}</p>}
                            </div>
                            {canBuild && (
                                <div className="shrink-0 flex gap-1.5 items-center">
                                    <Link href={`/forms/${f.slug}/submissions`} className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1.5">
                                        {f.submissions_count} response{f.submissions_count !== 1 && 's'}
                                    </Link>
                                    <button
                                        onClick={() => router.put(`/forms/${f.id}/toggle`, {}, { preserveScroll: true })}
                                        className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1.5"
                                    >
                                        {f.is_active ? 'Deactivate' : 'Activate'}
                                    </button>
                                </div>
                            )}
                        </div>
                    </Card>
                ))}
                {forms.length === 0 && <Card><EmptyState icon="📝" text="No forms yet." /></Card>}
            </div>

            {context && <Card title={`Forms already linked to ${context.name}`} className="mt-4"><div className="space-y-2">{linkedSubmissions.length === 0 && <p className="text-sm text-slate-500">No submitted forms are linked to this record yet.</p>}{linkedSubmissions.map((submission) => <div key={submission.id} className="rounded-xl bg-slate-50 p-3"><b className="text-sm text-brand-dark">{submission.form}</b><p className="text-xs text-slate-500">{new Date(submission.submitted_at).toLocaleString('en-GB')} · {submission.submitted_by}</p></div>)}</div></Card>}
        </AppShell>
    );
}
