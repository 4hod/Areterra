import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Template {
    id: number;
    name: string;
    subject: string;
    body: string;
}

interface Props {
    members: { id: number; name: string }[];
    member: { id: number; name: string } | null;
    contacts: { id: number; name: string; role: string | null; organisation: string | null; email: string | null }[];
    templates: Template[];
}

export default function EmailComposer({ members, member, contacts, templates }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        member_id: member?.id ?? ('' as string | number),
        to_email: '',
        to_name: '',
        organisation: '',
        subject: '',
        body: '',
        log_only: false,
        save_template: false,
        template_name: '',
    });

    function pickMember(id: string) {
        router.get('/email', id ? { member: id } : {}, { preserveState: false });
    }

    function applyTemplate(t: Template) {
        setData((d) => ({ ...d, subject: t.subject, body: t.body }));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/email/send', { onSuccess: () => reset('subject', 'body', 'save_template', 'template_name') });
    }

    return (
        <AppShell title="Email Composer">
            <Head title="Email Composer" />

            <form onSubmit={submit} className="space-y-4">
                <Card title="Who's it about?">
                    <select
                        value={data.member_id}
                        onChange={(e) => pickMember(e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 bg-white"
                    >
                        <option value="">Pick a member…</option>
                        {members.map((m) => (
                            <option key={m.id} value={m.id}>{m.name}</option>
                        ))}
                    </select>

                    {contacts.length > 0 && (
                        <div className="mt-3">
                            <div className="text-xs font-bold text-slate-400 uppercase mb-1">Circle of care — tap to fill</div>
                            <div className="flex flex-wrap gap-1.5">
                                {contacts.filter((c) => c.email).map((c) => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => setData((d) => ({ ...d, to_email: c.email ?? '', to_name: c.name, organisation: c.organisation ?? '' }))}
                                        className={`rounded-full border px-3 py-1.5 text-xs font-semibold ${
                                            data.to_email === c.email ? 'bg-brand text-white border-brand' : 'border-slate-200 hover:bg-slate-50'
                                        }`}
                                    >
                                        {c.name}{c.role && ` (${c.role})`}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </Card>

                <Card title="Message">
                    <div className="grid grid-cols-2 gap-3 mb-3">
                        <label className="block text-sm font-medium">
                            To (email)
                            <input type="email" value={data.to_email} onChange={(e) => setData('to_email', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                            {errors.to_email && <span className="text-red-600 text-xs">{errors.to_email}</span>}
                        </label>
                        <label className="block text-sm font-medium">
                            To (name)
                            <input value={data.to_name} onChange={(e) => setData('to_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    {templates.length > 0 && (
                        <label className="block text-sm font-medium mb-3">
                            Start from a template
                            <select
                                onChange={(e) => {
                                    const t = templates.find((x) => x.id === Number(e.target.value));
                                    if (t) applyTemplate(t);
                                }}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white"
                                defaultValue=""
                            >
                                <option value="">—</option>
                                {templates.map((t) => (
                                    <option key={t.id} value={t.id}>{t.name}</option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className="block text-sm font-medium">
                        Subject
                        <input value={data.subject} onChange={(e) => setData('subject', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <label className="block text-sm font-medium mt-3">
                        Message
                        <textarea value={data.body} onChange={(e) => setData('body', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={8} required />
                    </label>
                    <p className="text-xs text-slate-400 mt-1">
                        Merge tags: <code className="bg-slate-100 px-1 rounded">{'{{member_name}}'}</code>{' '}
                        <code className="bg-slate-100 px-1 rounded">{'{{today}}'}</code> — sent as a branded Areterra email,
                        Reply-To team@areterra.co.uk, and auto-logged to the Comms tab.
                    </p>

                    <div className="flex flex-wrap items-center gap-4 mt-3">
                        <label className="flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" checked={data.log_only} onChange={(e) => setData('log_only', e.target.checked)} className="rounded border-slate-300" />
                            Log only (already sent via Outlook)
                        </label>
                        <label className="flex items-center gap-2 text-sm font-medium">
                            <input type="checkbox" checked={data.save_template} onChange={(e) => setData('save_template', e.target.checked)} className="rounded border-slate-300" />
                            Save as template
                        </label>
                        {data.save_template && (
                            <input
                                value={data.template_name}
                                onChange={(e) => setData('template_name', e.target.value)}
                                placeholder="Template name"
                                className="rounded-lg border border-slate-300 px-3 !min-h-10 text-sm"
                            />
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing || !data.member_id}
                        className="mt-4 w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        {data.log_only ? '📝 Log email' : '✉️ Send & log'}
                    </button>
                </Card>
            </form>
        </AppShell>
    );
}
