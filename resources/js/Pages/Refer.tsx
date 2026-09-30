import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { SharedProps } from '../types';
import ModuleHero from '../components/ModuleHero';

// Public referral form — no login required.
export default function Refer() {
    const { flash, branding } = usePage<SharedProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        referrer_name: '',
        referrer_email: '',
        referrer_phone: '',
        organisation: '',
        person_name: '',
        details: '',
        authority_confirmed: false,
        privacy_acknowledged: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/refer', { onSuccess: () => reset() });
    }

    return (
        <>
            <Head title="Make a referral" />
            <ModuleHero eyebrow="New enquiry" title="Make a referral" description="Capture the right information and give every enquiry a confident start." icon="🤝" tone="teal" />
            <div className="min-h-screen bg-brand-dark py-10 px-4">
                <div className="max-w-xl mx-auto">
                    <div className="text-center text-white mb-6">
                        {branding.logoUrl ? (
                            <img src={branding.logoUrl} alt={branding.orgName} className="h-10 mx-auto mb-1 object-contain" />
                        ) : (
                            <div className="text-2xl font-extrabold">{branding.orgName}</div>
                        )}
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
                                    Email (email or phone required)
                                    <input type="email" value={data.referrer_email} onChange={(e) => setData('referrer_email', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                                </label>
                                <label className="block text-sm font-medium">
                                    Phone (email or phone required)
                                    <input value={data.referrer_phone} onChange={(e) => setData('referrer_phone', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                                </label>
                            </div>
                            {errors.referrer_name && <p className="text-red-600 text-sm">{errors.referrer_name}</p>}
                            {(errors.referrer_email || errors.referrer_phone) && <p className="text-red-600 text-sm">Please provide a valid email address or phone number so we can contact you.</p>}

                            <h2 className="font-bold text-brand-dark pt-2">Who are you referring?</h2>
                            <label className="block text-sm font-medium">
                                Their name *
                                <input value={data.person_name} onChange={(e) => setData('person_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                            </label>

                            <div className="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                <label className="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        checked={data.authority_confirmed}
                                        onChange={(e) => setData('authority_confirmed', e.target.checked)}
                                        className="mt-1 h-4 w-4"
                                        required
                                    />
                                    <span>I confirm I have the authority or the person's permission to make this referral and share this information. *</span>
                                </label>
                                <label className="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        checked={data.privacy_acknowledged}
                                        onChange={(e) => setData('privacy_acknowledged', e.target.checked)}
                                        className="mt-1 h-4 w-4"
                                        required
                                    />
                                    <span>I understand Areterra will use these details to assess and respond to this referral, and may contact me for more information. *</span>
                                </label>
                                {(errors.authority_confirmed || errors.privacy_acknowledged) && <p className="text-red-600">Both confirmations are required.</p>}
                            </div>
                            <label className="block text-sm font-medium">
                                Tell us about them and what they're looking for
                                <textarea value={data.details} onChange={(e) => setData('details', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={4} />
                            </label>

                            {/* Honeypot */}
                            <input type="text" name="website" tabIndex={-1} autoComplete="off" className="hidden" aria-hidden />

                            <button type="submit" disabled={processing || !data.authority_confirmed || !data.privacy_acknowledged} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
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
