import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import ModuleHero from '../../components/ModuleHero';

interface Field {
    id: number;
    label: string;
    type: 'text' | 'textarea' | 'select' | 'checkbox' | 'date' | 'number';
    options: string[] | null;
    is_required: boolean;
}

interface FormDef {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    fields: Field[];
}

interface Subject {
    type: string;
    id: number;
    name: string;
}

export default function Show({ form, subject }: { form: FormDef; subject: Subject | null }) {
    const initial: Record<string, string> = {};
    form.fields.forEach((f) => { initial[`field_${f.id}`] = f.type === 'checkbox' ? '' : ''; });

    // Carried through so the report lands on the record it's about.
    if (subject) {
        initial.about = subject.type;
        initial.about_id = String(subject.id);
    }

    const { data, setData, post, processing, errors, reset } = useForm(initial);

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/forms/${form.slug}/submissions`, { onSuccess: () => reset() });
    }

    return (
        <AppShell title={form.title}>
            <Head title={form.title} />
            <ModuleHero
                eyebrow={subject ? `About ${subject.name}` : 'Complete a record'}
                title={form.title}
                description="Capture accurate information in a calm, focused workspace."
                icon="✍️"
                tone="purple"
            />

            <Card>
                {subject && (
                    <div className="rounded-card bg-brand/5 border border-brand/20 px-4 py-3 mb-4 text-sm">
                        This will be saved to <b>{subject.name}</b>’s record.
                    </div>
                )}
                {form.description && <p className="text-sm text-slate-500 mb-4">{form.description}</p>}
                <form onSubmit={submit} className="space-y-4">
                    {form.fields.map((f) => {
                        const key = `field_${f.id}`;
                        return (
                            <label key={f.id} className="block text-sm font-medium">
                                {f.label}{f.is_required && <span className="text-red-500"> *</span>}

                                {f.type === 'textarea' && (
                                    <textarea value={data[key]} onChange={(e) => setData(key, e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} required={f.is_required} />
                                )}
                                {f.type === 'select' && (
                                    <select value={data[key]} onChange={(e) => setData(key, e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white" required={f.is_required}>
                                        <option value="">Select…</option>
                                        {(f.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
                                    </select>
                                )}
                                {f.type === 'checkbox' && (
                                    <div className="mt-1">
                                        <input
                                            type="checkbox"
                                            checked={data[key] === 'yes'}
                                            onChange={(e) => setData(key, e.target.checked ? 'yes' : '')}
                                            className="rounded border-slate-300"
                                        />
                                    </div>
                                )}
                                {f.type === 'date' && (
                                    <input type="date" value={data[key]} onChange={(e) => setData(key, e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required={f.is_required} />
                                )}
                                {f.type === 'number' && (
                                    <input type="number" value={data[key]} onChange={(e) => setData(key, e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required={f.is_required} />
                                )}
                                {f.type === 'text' && (
                                    <input value={data[key]} onChange={(e) => setData(key, e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required={f.is_required} />
                                )}
                                {errors[key] && <span className="text-red-600 text-xs block">{errors[key]}</span>}
                            </label>
                        );
                    })}
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Submit
                    </button>
                </form>
            </Card>
        </AppShell>
    );
}
