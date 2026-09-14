import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

interface Person { name: string; job_title: string | null; email: string | null; phone: string | null; photo_path?: string | null; }
interface Contact { id: number; name: string; category: string; organisation: string | null; email: string | null; phone: string | null; notes: string | null; }

export default function Directory({ staff, contacts, canManage }: { staff: Person[]; contacts: Contact[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ name: '', category: 'Vet', organisation: '', phone: '', email: '', notes: '' });
    function submit(e: FormEvent) { e.preventDefault(); post('/directory/contacts', { onSuccess: () => { setAdding(false); reset(); } }); }

    return <AppShell title="Directory">
        <Head title="Directory" />
        <ModuleHero eyebrow="People and contacts" title="Directory" description="Staff and useful external contacts in one place." icon="📖" tone="blue" />
        {canManage && <button onClick={() => setAdding(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">+ Add contact</button>}
        <h2 className="font-extrabold text-brand-dark mb-2">Useful contacts</h2>
        {contacts.length === 0 && <Card className="mb-5"><p className="text-sm text-slate-400">No external contacts yet. Add vets, suppliers, emergency contacts or other services here.</p></Card>}
        <div className="grid md:grid-cols-2 gap-2 mb-6">{contacts.map((c) => <Card key={c.id}><div className="flex gap-3 items-start"><div className="h-11 w-11 rounded-full bg-emerald-50 grid place-items-center">📞</div><div className="flex-1 min-w-0"><div className="text-xs font-bold uppercase text-emerald-700">{c.category}</div><div className="font-bold text-brand-dark">{c.name}</div>{c.organisation && <div className="text-xs text-slate-500">{c.organisation}</div>}<div className="flex gap-3 mt-2 text-sm">{c.phone && <a href={`tel:${c.phone}`} className="text-brand font-semibold">Call</a>}{c.email && <a href={`mailto:${c.email}`} className="text-brand font-semibold">Email</a>}</div>{c.notes && <p className="text-xs text-slate-500 mt-2">{c.notes}</p>}</div>{canManage && <button onClick={() => window.confirm(`Remove ${c.name}?`) && router.delete(`/directory/contacts/${c.id}`)} className="text-red-500 text-xs font-semibold">Remove</button>}</div></Card>)}</div>
        <h2 className="font-extrabold text-brand-dark mb-2">Staff</h2>
        <div className="space-y-2">{staff.map((s, i) => <Card key={i}><div className="flex items-center gap-3">{s.photo_path ? <img src={s.photo_path} alt={s.name} className="h-11 w-11 rounded-full object-cover" /> : <div className="h-11 w-11 rounded-full bg-brand/10 text-brand font-bold grid place-items-center">{s.name.charAt(0)}</div>}<div className="flex-1"><div className="font-bold text-brand-dark">{s.name}</div><div className="text-xs text-slate-400">{s.job_title}</div></div><div className="flex gap-2">{s.phone && <a href={`tel:${s.phone}`}>📞</a>}{s.email && <a href={`mailto:${s.email}`}>✉️</a>}</div></div></Card>)}</div>
        <Modal open={adding} title="Add directory contact" onClose={() => setAdding(false)}><form onSubmit={submit} className="space-y-3">
            <label className="block text-sm font-medium">Name<input value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required /></label>
            <div className="grid grid-cols-2 gap-3"><label className="block text-sm font-medium">Type<select value={data.category} onChange={(e) => setData('category', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3"><option>Vet</option><option>Supplier</option><option>Emergency</option><option>Professional</option><option>Other</option></select></label><label className="block text-sm font-medium">Organisation<input value={data.organisation} onChange={(e) => setData('organisation', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label></div>
            <div className="grid grid-cols-2 gap-3"><label className="block text-sm font-medium">Phone<input value={data.phone} onChange={(e) => setData('phone', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label><label className="block text-sm font-medium">Email<input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" /></label></div>
            <label className="block text-sm font-medium">Notes<textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} /></label>
            <button disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3">Add contact</button>
        </form></Modal>
    </AppShell>;
}
