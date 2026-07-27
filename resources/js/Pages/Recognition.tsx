import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Row {
    id: number;
    author: string;
    recipient: string;
    message: string;
    created_at: string;
    likes: number;
    liked_by_me: boolean;
}

export default function Recognition({ recognitions }: { recognitions: Row[] }) {
    const [posting, setPosting] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ recipient: '', message: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/recognition', { onSuccess: () => { setPosting(false); reset(); } });
    }

    return (
        <AppShell title="Recognition">
            <Head title="Recognition" />
            <ModuleHero eyebrow="Celebrate the team" title="Recognition" description="Notice great work and make appreciation part of everyday culture." icon="🌟" tone="amber" />

            <button onClick={() => setPosting(true)} className="rounded-full bg-accent text-brand-dark font-bold text-sm px-5 py-2.5 mb-4">
                🌟 Give a shoutout
            </button>

            {recognitions.length === 0 && (
                <Card><p className="text-slate-500">No shoutouts yet — be the first! 🌟</p></Card>
            )}

            <div className="space-y-3">
                {recognitions.map((r) => (
                    <Card key={r.id}>
                        <div className="text-sm">
                            <b className="text-brand-dark">{r.author}</b>
                            <span className="text-slate-400"> gave a shoutout to </span>
                            <b className="text-brand-dark">{r.recipient}</b>
                        </div>
                        <p className="mt-1">{r.message}</p>
                        <div className="mt-2 flex items-center justify-between">
                            <button
                                onClick={() => router.post(`/recognition/${r.id}/like`)}
                                className={`rounded-full text-xs font-bold px-3 py-1.5 ${
                                    r.liked_by_me ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                👍 {r.likes}
                            </button>
                            <span className="text-xs text-slate-400">
                                {new Date(r.created_at.replace(' ', 'T')).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}
                            </span>
                        </div>
                    </Card>
                ))}
            </div>

            <Modal open={posting} title="Give a shoutout 🌟" onClose={() => setPosting(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Who's it for?
                        <input value={data.recipient} onChange={(e) => setData('recipient', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <label className="block text-sm font-medium">
                        What did they do?
                        <textarea value={data.message} onChange={(e) => setData('message', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} required />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Post shoutout
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}
