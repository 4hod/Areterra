import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Row {
    id: number;
    title: string;
    body: string;
    author: string;
    created_at: string;
    read: boolean;
}

export default function Announcements({ announcements, canPost }: { announcements: Row[]; canPost: boolean }) {
    const [posting, setPosting] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ title: '', body: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/announcements', {
            onSuccess: () => {
                setPosting(false);
                reset();
            },
        });
    }

    return (
        <AppShell title="Announcements">
            <Head title="Announcements" />
            <ModuleHero eyebrow="Team communications" title="Announcements" description="Share important updates and keep the whole team aligned." icon="📢" tone="blue" />

            {canPost && (
                <button
                    onClick={() => setPosting(true)}
                    className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4"
                >
                    + Post announcement
                </button>
            )}

            {announcements.length === 0 && (
                <Card>
                    <p className="text-slate-500">No announcements yet.</p>
                </Card>
            )}

            <div className="space-y-3">
                {announcements.map((a) => (
                    <Card key={a.id} className={a.read ? 'opacity-75' : 'border-l-4 border-l-brand'}>
                        <div className="flex items-start justify-between gap-2">
                            <div>
                                <div className="font-bold text-brand-dark">{a.title}</div>
                                <div className="text-xs text-slate-400 mb-2">
                                    {a.author} · {new Date(a.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                </div>
                                <p className="text-sm whitespace-pre-wrap">{a.body}</p>
                            </div>
                            {!a.read && (
                                <button
                                    onClick={() => router.post(`/announcements/${a.id}/read`)}
                                    className="shrink-0 rounded-full bg-slate-100 text-slate-600 text-xs font-bold px-3 py-2"
                                >
                                    Mark read
                                </button>
                            )}
                        </div>
                    </Card>
                ))}
            </div>

            <Modal open={posting} title="Post announcement" onClose={() => setPosting(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Title
                        <input
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            required
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Message
                        <textarea
                            value={data.body}
                            onChange={(e) => setData('body', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={4}
                            required
                        />
                    </label>
                    <p className="text-xs text-slate-400">All staff will get a push notification (or email fallback).</p>
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Post
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
