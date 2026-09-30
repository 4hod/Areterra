import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppIcon from '../components/AppIcon';

const FEATURES = [
    { icon: 'paw', label: 'Animal welfare and care records' },
    { icon: 'users', label: 'Member support plans and history' },
    { icon: 'register', label: 'Daily attendance and fire register' },
    { icon: 'shield', label: 'Secure, permission-controlled access' },
];

interface Props { ssoConfigured: boolean; localPasswordEnabled: boolean; loginPhotoUrl: string | null; logoUrl: string | null }

export default function Login({ ssoConfigured, localPasswordEnabled, loginPhotoUrl, logoUrl }: Props) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '' });
    function submit(event: FormEvent) { event.preventDefault(); post('/login'); }

    return <>
        <Head title="Log in"/>
        <main className="login-v2">
            <section className="login-v2-visual" style={loginPhotoUrl ? { backgroundImage: `linear-gradient(120deg,rgba(0,57,85,.97),rgba(0,69,104,.82)),url(${loginPhotoUrl})` } : undefined}>
                <div className="login-v2-brand">{logoUrl ? <img src={logoUrl} alt="Areterra"/> : <strong>Areterra</strong>}</div>
                <div className="login-v2-copy"><span>SECURE STAFF WORKSPACE</span><h1>People, animals<br/>and priorities.<br/><em>All in one place.</em></h1><p>Supporting safe, consistent care with clear records and simple daily workflows.</p><div>{FEATURES.map((feature) => <article key={feature.label}><i><AppIcon name={feature.icon}/></i><b>{feature.label}</b></article>)}</div></div>
                <footer><a href="https://areterra.co.uk">Main website</a><a href="mailto:team@areterra.co.uk">Get help</a><span>Registered charity No. 1196211</span></footer>
            </section>

            <section className="login-v2-form-panel">
                <form onSubmit={submit}>
                    <span className="login-v2-badge"><AppIcon name="lock"/> AUTHORISED ACCESS</span>
                    <h2>Welcome back</h2><p>Sign in to continue to the Areterra Hub.</p>

                    {ssoConfigured && <button type="button" className="login-v2-microsoft" onClick={() => window.location.href = '/auth/microsoft'}>
                        <svg viewBox="0 0 21 21" aria-hidden><rect x="1" y="1" width="9" height="9" fill="#f25022"/><rect x="11" y="1" width="9" height="9" fill="#7fba00"/><rect x="1" y="11" width="9" height="9" fill="#00a4ef"/><rect x="11" y="11" width="9" height="9" fill="#ffb900"/></svg>
                        Sign in with Microsoft
                    </button>}

                    {localPasswordEnabled && <>
                        {ssoConfigured && <div className="login-v2-divider"><i/>or use your portal password<i/></div>}
                        <label>Email address<input type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} autoComplete="username" required/>{errors.email && <small>{errors.email}</small>}</label>
                        <label>Password<div><input type={showPassword ? 'text' : 'password'} value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="current-password" required/><button type="button" onClick={() => setShowPassword((value) => !value)}>{showPassword ? 'Hide' : 'Show'}</button></div></label>
                        <button type="submit" className="login-v2-submit" disabled={processing}>{processing ? 'Signing in…' : 'Sign in'}</button>
                    </>}

                    {!ssoConfigured && !localPasswordEnabled && <div className="login-v2-unavailable"><AppIcon name="alert"/><p>Sign-in has not been configured. Please contact an administrator.</p></div>}
                    <small className="login-v2-privacy"><AppIcon name="shield"/> Client information is confidential and access is audited.</small>
                </form>
            </section>
        </main>
    </>;
}
