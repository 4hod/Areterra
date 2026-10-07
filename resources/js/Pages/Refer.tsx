import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import { SharedProps } from '../types';

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
            <div className="refer-public-4a">
                <header className="refer-public-head-4a">
                    <div>
                        {branding.logoUrl ? (
                            <img src={branding.logoUrl} alt={branding.orgName} />
                        ) : (
                            <strong>{branding.orgName}</strong>
                        )}
                        <span>People · Animals · Brighter Futures</span>
                    </div>
                    <a href="https://areterra.co.uk">Visit Areterra</a>
                </header>
                <main className="refer-public-main-4a">
                    <section className="refer-public-intro-4a"><span>New enquiry</span><h1>Make a referral</h1><p>Tell us about the person and what they are looking for. Our team will review the enquiry and contact you about the right next step.</p><div><b>1</b><span>Share the essentials</span><b>2</b><span>We review the referral</span><b>3</b><span>We contact you</span></div></section>
                    <div className="refer-public-form-wrap-4a">

                    {flash.success ? (
                        <div className="bg-white rounded-2xl shadow-xl p-8 text-center">
                            <div className="text-4xl mb-2">💚</div>
                            <p className="font-bold text-brand-dark">{flash.success}</p>
                        </div>
                    ) : (
                        <form onSubmit={submit} className="refer-public-form-4a bg-white rounded-2xl shadow-xl p-6 space-y-4">
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
                        </form>
                    )}
                    </div>
                </main>
                <footer className="refer-public-footer-4a">Areterra · Registered charity No. 1196211 · 01562 307 306 · team@areterra.co.uk</footer>
                </div>
        </>
    );
}
