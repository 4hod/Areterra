import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { SharedProps } from '../types';

// Public referral form — no login required.
export default function Refer() {
    const { flash } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        referrer_name: '',
        referrer_email: '',
        referrer_phone: '',
        organisation: '',
        person_name: '',
        details: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/refer', { onSuccess: () => reset() });
    }

    return (
        <>
            <Head title="Make a referral" />
            <div className="min-h-screen bg-brand-dark py-10 px-4">
                <div className="max-w-xl mx-auto">
                    <div className="text-center text-white mb-6">
                        <div className="text-2xl font-extrabold">Areterra</div>
                        <div className="text-white/60 text-sm">Animals. People. Purpose.</div>
                        <h1 className="text-xl font-bold mt-4">Make a referral</h1>
                        <p className="text-white/70 text-sm mt-1">
                            Refer an adult with learning disabilities to our day opportunity services.
                        </p>
                    </div>

                    {flash.success ? (
                        <div className="bg-white rounded-2xl shadow-xl p-8 text-center">
                            <div className="text-4xl mb-2">💚</div>
                            <p className="font-bold text-brand-dark">{flash.success}</p>
                        </div>
                    ) : (
                        <form onSubmit={submit} className="bg-white rounded-2xl shadow-xl p-6 space-y-4">
                            <h2 className="font-bold text-brand-dark">About you</h2>
                            <div className="grid sm:grid-cols-2 gap-3">
                                <label className="block text-sm font-medium">
                                    Your name *
                                    <input value={data.referrer_name} onChange={(e) => setData('referrer_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                                </label>
                                <label className="block text-sm font-medium">
                                    Organisation
                                    <input value={data.organisation} onChange={(e) => setData('organisation', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                                </label>
                                <label className="block text-sm font-medium">
                                    Email
                                    <input type="email" value={data.referrer_email} onChange={(e) => setData('referrer_email', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                                </label>
                                <label className="block text-sm font-medium">
                                    Phone
                                    <input value={data.referrer_phone} onChange={(e) => setData('referrer_phone', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                                </label>
                            </div>
                            {errors.referrer_name && <p className="text-red-600 text-sm">{errors.referrer_name}</p>}

                            <h2 className="font-bold text-brand-dark pt-2">Who are you referring?</h2>
                            <label className="block text-sm font-medium">
                                Their name *
                                <input value={data.person_name} onChange={(e) => setData('person_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                            </label>
                            <label className="block text-sm font-medium">
                                Tell us about them and what they're looking for
                                <textarea value={data.details} onChange={(e) => setData('details', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={4} />
                            </label>

                            {/* Honeypot */}
                            <input type="text" name="website" tabIndex={-1} autoComplete="off" className="hidden" aria-hidden />

                            <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                                Send referral
                            </button>
                            <p className="text-[11px] text-slate-400 text-center">
                                Areterra · Registered charity No. 1196211 · 01562 307 306 · team@areterra.co.uk
                            </p>
                        </form>
                    )}
                </div>
            </div>
        </>
    );
}
