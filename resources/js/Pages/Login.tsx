import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const FEATURES = [
    { icon: '🐾', label: 'Animal welfare & care records' },
    { icon: '👥', label: 'Member support plans & history' },
    { icon: '📋', label: 'Daily records & end of day notes' },
    { icon: '🔒', label: 'GDPR-compliant & secure' },
];

export default function Login({ ssoConfigured, loginPhotoUrl, logoUrl }: { ssoConfigured: boolean; loginPhotoUrl: string | null; logoUrl: string | null }) {
    const [showPassword, setShowPassword] = useState(false);
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
            <div className="min-h-screen flex bg-brand-dark relative overflow-hidden">
                {/* Left panel */}
                <div className="flex-1 flex flex-col justify-between px-6 py-10 md:px-16 text-white relative z-10">
                    <div>
                        <img
                            src={logoUrl || 'https://areterra.co.uk/wp-content/uploads/2023/01/Areterra-logo-3-transparent_.png'}
                            alt="Areterra"
                            className="h-11 mb-4"
                        />
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 border border-white/15 px-3 py-1 text-[11px] font-bold tracking-wider text-white/80">
                            <span className="h-1.5 w-1.5 rounded-full bg-accent" aria-hidden />
                            STAFF PORTAL
                        </span>

                        <h1 className="text-4xl md:text-5xl font-extrabold leading-[1.05] mt-8">
                            Animals.
                            <br />
                            <span className="text-brand">People.</span>
                            <br />
                            Purpose.
                        </h1>
                        <p className="mt-4 max-w-sm text-white/60 text-[15px] leading-relaxed">
                            Supporting adults with learning disabilities through animal care, horticulture and
                            practical skills.
                        </p>

                        <ul className="hidden md:block mt-7 space-y-3">
                            {FEATURES.map((f) => (
                                <li key={f.label} className="flex items-center gap-3 text-white/70 text-sm">
                                    <span className="h-8 w-8 rounded-lg bg-white/10 flex items-center justify-center text-base shrink-0">
                                        {f.icon}
                                    </span>
                                    {f.label}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="flex items-center gap-4 text-xs text-white/40">
                        <a href="https://areterra.co.uk" className="hover:text-white/70">← Main website</a>
                        <a href="mailto:team@areterra.co.uk" className="hover:text-white/70">Get help</a>
                        <span className="ml-auto">© {new Date().getFullYear()} Areterra · Charity No. 1196211</span>
                    </div>
                </div>

                {/* Right panel (desktop only) */}
                <div className="hidden lg:block flex-1 relative">
                    {loginPhotoUrl ? (
                        <>
                            <img src={loginPhotoUrl} alt="" className="absolute inset-0 h-full w-full object-cover" />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/10" />
                        </>
                    ) : (
                        <div className="absolute inset-0 bg-gradient-to-br from-brand to-brand-dark flex items-center justify-center text-6xl">
                            🦜🐰🐹
                        </div>
                    )}
                    <div className="absolute bottom-8 right-8 text-white/90 text-lg font-semibold italic drop-shadow">
                        "Animals. People. Purpose."
                    </div>
                </div>

                {/* Floating form card — overlaps both panels, centred on the boundary */}
                <div className="absolute inset-0 flex items-center justify-center lg:justify-start px-6 lg:px-0 z-20 pointer-events-none">
                    <form
                        onSubmit={submit}
                        className="pointer-events-auto w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 text-ink space-y-4 lg:ml-[calc(50%-13rem)]"
                    >
                        <div>
                            <label htmlFor="email" className="block text-[11px] font-bold tracking-wider text-ink/50 uppercase mb-1.5">
                                Username or email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="w-full rounded-lg border border-black/10 bg-brand/5 px-3 focus:border-brand focus:ring-brand"
                                autoComplete="username"
                                required
                            />
                            {errors.email && <p className="text-red-600 text-sm mt-1">{errors.email}</p>}
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-[11px] font-bold tracking-wider text-ink/50 uppercase mb-1.5">
                                Password
                            </label>
                            <div className="relative">
                                <input
                                    id="password"
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="w-full rounded-lg border border-black/10 bg-brand/5 px-3 pr-11 focus:border-brand focus:ring-brand"
                                    autoComplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword((s) => !s)}
                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                    className="absolute right-0 top-0 h-full px-3 text-ink/30 hover:text-ink/60"
                                >
                                    {showPassword ? '🙈' : '👁️'}
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            disabled={!ssoConfigured}
                            onClick={() => ssoConfigured && (window.location.href = '/auth/microsoft')}
                            title={ssoConfigured ? 'Sign in with Microsoft' : 'Set up in Hub Settings'}
                            className={`w-full flex items-center justify-center gap-2 rounded-lg border px-4 py-3 text-sm font-semibold ${
                                ssoConfigured
                                    ? 'border-black/10 hover:bg-black/[0.03]'
                                    : 'border-black/5 text-ink/30 cursor-not-allowed'
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

                        <div className="flex items-center gap-3 text-xs text-ink/35">
                            <div className="h-px flex-1 bg-black/10" />
                            or sign in with password
                            <div className="h-px flex-1 bg-black/10" />
                        </div>

                        <label className="flex items-center gap-2 text-sm text-ink/70">
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="rounded border-black/20"
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
                </div>

                <a
                    href="https://rockitfox.co.uk"
                    target="_blank"
                    rel="noreferrer"
                    className="absolute bottom-4 right-4 flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-[11px] font-semibold text-white/70 hover:bg-white/20 z-30"
                >
                    <span className="text-orange-400" aria-hidden>🚀</span>
                    Site designed &amp; built by RockitFox
                </a>
            </div>
        </>
    );
}
