import { Head, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import { SharedProps } from '../../types';
import ModuleHero from '../../components/ModuleHero';

interface Props {
    policy: {
        id: number;
        title: string;
        body: string;
        version: string;
        review_date: string | null;
        status: string;
        updated_at: string;
    };
    canManage: boolean;
}

export default function Show({ policy, canManage }: Props) {
    const { branding } = usePage<SharedProps>().props;
    const [editing, setEditing] = useState(false);
    const [version, setVersion] = useState(policy.version);
    const [status, setStatus] = useState(policy.status);
    const bodyRef = useRef<HTMLDivElement>(null);

    function exec(command: string, value?: string) {
        document.execCommand(command, false, value);
        bodyRef.current?.focus();
    }

    function save() {
        router.put(
            `/policies/${policy.id}`,
            {
                title: policy.title,
                body: bodyRef.current?.innerHTML ?? policy.body,
                version,
                status,
                review_date: policy.review_date,
            },
            { onSuccess: () => setEditing(false) },
        );
    }

    return (
        <AppShell title={policy.title}>
            <Head title={policy.title}>
                <style>{`@media print {
                    aside, header, nav, .no-print { display: none !important }
                    main { padding: 0 !important; max-width: none !important }
                    .print-header { display: flex !important }
                }`}</style>
            </Head>
            <ModuleHero eyebrow="Policy detail" title="Policy" description="Read the current version, key details and acknowledgement status." icon="📖" tone="amber" />

            {/* Print-only branded header */}
            <div className="print-header hidden justify-between items-start mb-6">
                {branding.logoUrl ? (
                    <img src={branding.logoUrl} alt={branding.orgName} className="h-10 object-contain object-left" />
                ) : (
                    <div className="text-2xl font-extrabold" style={{ color: '#00345C' }}>{branding.orgName}</div>
                )}
                <div className="text-right text-xs">
                    <div>Version {policy.version}</div>
                    <div>{new Date(policy.updated_at).toLocaleDateString('en-GB')}</div>
                </div>
            </div>

            <div className="no-print flex flex-wrap items-center gap-2 mb-4 text-sm">
                <span className="text-slate-500">
                    v{policy.version} · {policy.status}
                    {policy.review_date && ` · review due ${new Date(policy.review_date).toLocaleDateString('en-GB')}`}
                </span>
                <span className="ml-auto flex gap-2">
                    <button onClick={() => window.print()} className="rounded-full bg-brand-dark text-white font-semibold text-xs px-4 py-2">
                        🖨 Print PDF
                    </button>
                    {canManage && !editing && (
                        <>
                            <button onClick={() => setEditing(true)} className="rounded-full bg-brand text-white font-semibold text-xs px-4 py-2">
                                ✏️ Edit
                            </button>
                            <button
                                onClick={() => router.post(`/policies/${policy.id}/approve`)}
                                className="rounded-full bg-status-green text-white font-semibold text-xs px-4 py-2"
                            >
                                ✓ Approve
                            </button>
                        </>
                    )}
                </span>
            </div>

            {editing && (
                <div className="no-print mb-2 flex flex-wrap items-center gap-1.5">
                    {(
                        [
                            ['bold', 'B'],
                            ['italic', 'I'],
                            ['insertUnorderedList', '• List'],
                            ['insertOrderedList', '1. List'],
                        ] as const
                    ).map(([cmd, label]) => (
                        <button key={cmd} onClick={() => exec(cmd)} className="rounded bg-slate-100 font-bold text-xs px-3 py-2">
                            {label}
                        </button>
                    ))}
                    <button onClick={() => exec('formatBlock', 'h2')} className="rounded bg-slate-100 font-bold text-xs px-3 py-2">
                        Heading
                    </button>
                    <input
                        value={version}
                        onChange={(e) => setVersion(e.target.value)}
                        className="w-16 rounded border border-slate-300 px-2 !min-h-9 text-xs"
                        aria-label="Version"
                    />
                    <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border border-slate-300 px-2 !min-h-9 text-xs bg-white">
                        <option value="draft">draft</option>
                        <option value="active">active</option>
                        <option value="archived">archived</option>
                    </select>
                    <button onClick={save} className="rounded-full bg-brand text-white font-bold text-xs px-4 py-2">
                        Save
                    </button>
                </div>
            )}

            <Card>
                <h1 className="text-xl font-extrabold text-brand-dark mb-3">{policy.title}</h1>
                <div
                    ref={bodyRef}
                    contentEditable={editing}
                    suppressContentEditableWarning
                    className={`prose prose-sm max-w-none [&_h2]:font-bold [&_h2]:text-brand-dark [&_h2]:mt-4 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 ${
                        editing ? 'outline outline-2 outline-brand/40 rounded p-2 min-h-40' : ''
                    }`}
                    dangerouslySetInnerHTML={{ __html: policy.body }}
                />
            </Card>

            <div className="print-header hidden text-[10px] mt-8">
                Areterra · Little Croft, Fenn Green, WV15 6JA · 01562 307 306 · Registered charity No. 1196211
            </div>
        </AppShell>
    );
}
