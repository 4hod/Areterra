import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';

interface FieldDraft {
    label: string;
    type: 'text' | 'textarea' | 'select' | 'checkbox' | 'date' | 'number';
    options: string; // comma-separated, only used for 'select'
    is_required: boolean;
}

const BLANK_FIELD: FieldDraft = { label: '', type: 'text', options: '', is_required: false };

export default function Builder() {
    const { data, setData, post, transform, processing, errors } = useForm<{
        title: string;
        description: string;
        fields: FieldDraft[];
    }>({
        title: '',
        description: '',
        fields: [{ ...BLANK_FIELD }],
    });

    function updateField(i: number, patch: Partial<FieldDraft>) {
        const fields = [...data.fields];
        fields[i] = { ...fields[i], ...patch };
        setData('fields', fields);
    }

    function addField() {
        setData('fields', [...data.fields, { ...BLANK_FIELD }]);
    }

    function removeField(i: number) {
        setData('fields', data.fields.filter((_, idx) => idx !== i));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        transform((d) => ({
            ...d,
            fields: d.fields.map((f) => ({
                ...f,
                options: f.type === 'select' ? f.options.split(',').map((o) => o.trim()).filter(Boolean) : null,
            })),
        }));
        post('/forms');
    }

    return (
        <AppShell title="New form">
            <Head title="New form" />

            <form onSubmit={submit} className="space-y-4">
                <Card>
                    <label className="block text-sm font-medium mb-3">
                        Form title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        {errors.title && <span className="text-red-600 text-xs">{errors.title}</span>}
                    </label>
                    <label className="block text-sm font-medium">
                        Description (optional)
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={2} />
                    </label>
                </Card>

                <Card title="Fields">
                    <div className="space-y-3">
                        {data.fields.map((field, i) => (
                            <div key={i} className="border border-slate-200 rounded-lg p-3">
                                <div className="flex gap-2 mb-2">
                                    <input
                                        value={field.label}
                                        onChange={(e) => updateField(i, { label: e.target.value })}
                                        placeholder="Field label"
                                        className="flex-1 rounded-lg border border-slate-300 px-3"
                                        required
                                    />
                                    <select
                                        value={field.type}
                                        onChange={(e) => updateField(i, { type: e.target.value as FieldDraft['type'] })}
                                        className="rounded-lg border border-slate-300 px-2 bg-white"
                                    >
                                        <option value="text">Text</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="select">Select</option>
                                        <option value="checkbox">Checkbox</option>
                                        <option value="date">Date</option>
                                        <option value="number">Number</option>
                                    </select>
                                    {data.fields.length > 1 && (
                                        <button type="button" onClick={() => removeField(i)} className="text-red-500 font-bold px-2">✕</button>
                                    )}
                                </div>
                                {field.type === 'select' && (
                                    <input
                                        value={field.options}
                                        onChange={(e) => updateField(i, { options: e.target.value })}
                                        placeholder="Comma-separated options, e.g. Yes, No, Maybe"
                                        className="w-full rounded-lg border border-slate-300 px-3 mb-2 text-sm"
                                    />
                                )}
                                <label className="flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <input type="checkbox" checked={field.is_required} onChange={(e) => updateField(i, { is_required: e.target.checked })} className="rounded border-slate-300" />
                                    Required
                                </label>
                            </div>
                        ))}
                    </div>
                    <button type="button" onClick={addField} className="mt-3 rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2">
                        + Add field
                    </button>
                </Card>

                <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                    Create form
                </button>
            </form>
        </AppShell>
    );
}
