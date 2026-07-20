import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Login({ ssoConfigured }: { ssoConfigured: boolean }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/login');
    }

    return (
        <>
            <Head title="Log in" />
            <div className="min-h-screen flex bg-brand-dark">
                {/* Left panel */}
                <div className="flex-1 flex flex-col justify-center px-6 py-10 md:px-16 text-white relative">
                    <div className="max-w-md mx-auto w-full">
                        <img
                            src="https://areterra.co.uk/wp-content/uploads/2023/01/Areterra-logo-3-transparent_.png"
                            alt="Areterra"
                            className="h-14 mb-6"
                        />
                        <h1 className="text-3xl md:text-4xl font-extrabold leading-tight">
                            Animals. People.
                            <br />
                            Purpose.
                        </h1>
                        <ul className="hidden md:block mt-6 space-y-2 text-white/70 text-sm">
                            <li>• Daily registers, moods and end-of-day handover</li>
                            <li>• Animal welfare checks and monitoring</li>
                            <li>• Everything in one place, on any device</li>
                        </ul>

                        {/* Glassmorphic form card */}
                        <form
                            onSubmit={submit}
                            className="mt-8 bg-white/95 backdrop-blur rounded-2xl shadow-2xl p-6 text-slate-900 space-y-4"
                        >
                            <button
                                type="button"
                                disabled={!ssoConfigured}
                                onClick={() => ssoConfigured && (window.location.href = '/auth/microsoft')}
                                title={ssoConfigured ? 'Sign in with Microsoft' : 'Set up in Hub Settings'}
                                className={`w-full flex items-center justify-center gap-2 rounded-lg border px-4 py-3 text-sm font-semibold ${
                                    ssoConfigured
                                        ? 'border-slate-300 hover:bg-slate-50'
                                        : 'border-slate-200 text-slate-400 cursor-not-allowed'
                                }`}
                            >
                                <svg viewBox="0 0 21 21" className="h-4 w-4" aria-hidden>
                                    <rect x="1" y="1" width="9" height="9" fill="#f25022" />
                                    <rect x="11" y="1" width="9" height="9" fill="#7fba00" />
                                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef" />
                                    <rect x="11" y="11" width="9" height="9" fill="#ffb900" />
                                </svg>
                                Sign in with Microsoft
                                {!ssoConfigured && <span className="text-xs font-normal">(set up in Hub Settings)</span>}
                            </button>

                            <div className="flex items-center gap-3 text-xs text-slate-400">
                                <div className="h-px flex-1 bg-slate-200" />
                                or sign in with password
                                <div className="h-px flex-1 bg-slate-200" />
                            </div>

                            <div>
                                <label htmlFor="email" className="block text-sm font-medium mb-1">
                                    Email
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 focus:border-brand focus:ring-brand"
                                    autoComplete="username"
                                    required
                                />
                                {errors.email && <p className="text-red-600 text-sm mt-1">{errors.email}</p>}
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-sm font-medium mb-1">
                                    Password
                                </label>
                                <input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 focus:border-brand focus:ring-brand"
                                    autoComplete="current-password"
                                    required
                                />
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="rounded border-slate-300"
                                />
                                Remember me
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-brand hover:bg-brand-dark text-white font-bold py-3 transition disabled:opacity-60"
                            >
                                Log In
                            </button>
                        </form>

                        <p className="mt-6 text-xs text-white/50">
                            Areterra · Registered charity No. 1196211
                        </p>
                    </div>
                </div>

                {/* Right panel (desktop only) */}
                <div className="hidden lg:block flex-1 relative bg-brand">
                    <div className="absolute inset-0 bg-gradient-to-br from-brand to-brand-dark" />
                    <div className="absolute bottom-10 left-10 text-white">
                        <div className="text-5xl mb-3" aria-hidden>
                            🦜🐰🐹
                        </div>
                        <div className="text-2xl font-extrabold">Animals. People. Purpose.</div>
                    </div>
                </div>
            </div>
        </>
    );
}
