import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';

interface Doc {
    id: number;
    title: string;
    category: string | null;
    original_name: string;
    requires_read: boolean;
    uploaded_by: string;
    created_at: string;
    read_by_me: boolean;
    read_count: number;
    staff_count: number;
}

export default function Documents({ documents, canUpload }: { documents: Doc[]; canUpload: boolean }) {
    const [uploading, setUploading] = useState(false);
    const { data, setData, post, processing, reset } = useForm<{
        title: string;
        category: string;
        file: File | null;
        requires_read: boolean;
    }>({ title: '', category: '', file: null, requires_read: false });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/documents', {
            forceFormData: true,
            onSuccess: () => {
                setUploading(false);
                reset();
            },
        });
    }

    return (
        <AppShell title="Documents">
            <Head title="Documents" />

            {canUpload && (
                <button onClick={() => setUploading(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + Upload document
                </button>
            )}

            {documents.length === 0 && (
                <Card><p className="text-slate-500">No documents yet.</p></Card>
            )}

            <div className="space-y-2">
                {documents.map((d) => (
                    <Card key={d.id} className={d.requires_read && !d.read_by_me ? 'border-l-4 border-l-status-amber' : ''}>
                        <div className="flex items-center justify-between gap-2">
                            <div className="min-w-0">
                                <div className="font-bold text-brand-dark truncate">📄 {d.title}</div>
                                <div className="text-xs text-slate-400">
                                    {d.category && `${d.category} · `}
                                    {d.uploaded_by} · {new Date(d.created_at).toLocaleDateString('en-GB')}
                                    {d.requires_read && ` · read by ${d.read_count}/${d.staff_count}`}
                                </div>
                            </div>
                            <div className="flex gap-1 shrink-0">
                                <a
                                    href={`/documents/${d.id}/download`}
                                    className="rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2"
                                >
                                    ⬇ Download
                                </a>
                                {d.requires_read && !d.read_by_me && (
                                    <button
                                        onClick={() => router.post(`/documents/${d.id}/read`)}
                                        className="rounded-full bg-brand text-white text-xs font-bold px-3 py-2"
                                    >
                                        ✓ Mark read
                                    </button>
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>

            <Modal open={uploading} title="Upload document" onClose={() => setUploading(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <label className="block text-sm font-medium">
                        Category
                        <input value={data.category} onChange={(e) => setData('category', e.target.value)} placeholder="e.g. HR, Training, H&S" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                    </label>
                    <label className="block text-sm font-medium">
                        File
                        <input
                            type="file"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"
                            required
                        />
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" checked={data.requires_read} onChange={(e) => setData('requires_read', e.target.checked)} className="rounded border-slate-300" />
                        Require read confirmation from all staff
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Upload
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
