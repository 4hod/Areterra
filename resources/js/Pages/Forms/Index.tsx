import { Head, Link, router } from '@inertiajs/react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import EmptyState from '../../components/EmptyState';

interface FormRow {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    submissions_count: number;
}

export default function Index({ forms, canBuild }: { forms: FormRow[]; canBuild: boolean }) {
    return (
        <AppShell title="Forms">
            <Head title="Forms" />

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
                                <Link href={`/forms/${f.slug}`} className="font-bold text-brand-dark hover:underline">
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
        </AppShell>
    );
}
