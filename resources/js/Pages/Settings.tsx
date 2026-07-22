import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Props {
    settings: {
        org_name: string;
        logo_url: string | null;
        login_photo_url: string | null;
        reply_to: string;
        banner_text: string | null;
        ms_client_id: string | null;
        ms_tenant_id: string | null;
        ms_client_secret_set: boolean;
    };
}

export default function Settings({ settings }: Props) {
    const { data, setData, put, processing } = useForm({
        org_name: settings.org_name,
        logo_url: settings.logo_url ?? '',
        login_photo_url: settings.login_photo_url ?? '',
        reply_to: settings.reply_to,
        banner_text: settings.banner_text ?? '',
        ms_client_id: settings.ms_client_id ?? '',
        ms_tenant_id: settings.ms_tenant_id ?? '',
        ms_client_secret: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        put('/settings');
    }

    return (
        <AppShell title="Hub Settings">
            <Head title="Hub Settings" />

            <form onSubmit={submit} className="space-y-4">
                <Card title="Organisation">
                    <div className="space-y-3">
                        <label className="block text-sm font-medium">
                            Organisation name
                            <input value={data.org_name} onChange={(e) => setData('org_name', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Logo URL
                            <input type="url" value={data.logo_url} onChange={(e) => setData('logo_url', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Login page photo URL
                            <input type="url" value={data.login_photo_url} onChange={(e) => setData('login_photo_url', e.target.value)} placeholder="Photo shown on the right panel of the login screen" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Reply-to email
                            <input type="email" value={data.reply_to} onChange={(e) => setData('reply_to', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Dashboard banner text
                            <input value={data.banner_text} onChange={(e) => setData('banner_text', e.target.value)} placeholder="Optional announcement banner shown on the dashboard" className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                </Card>

                <Card title="Microsoft sign-in (Azure)">
                    <p className="text-xs text-slate-400 mb-3">
                        From your Azure app registration. Redirect URI: <code className="bg-slate-100 px-1 rounded">{window.location.origin}/ah-ms-callback</code>
                    </p>
                    <p className="text-xs text-amber-600 mb-3">
                        Tenant ID is required alongside Client ID — without it, sign-in stays disabled rather than
                        falling back to accepting any Microsoft account.
                    </p>
                    <div className="space-y-3">
                        <label className="block text-sm font-medium">
                            Client ID
                            <input value={data.ms_client_id} onChange={(e) => setData('ms_client_id', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Tenant ID
                            <input value={data.ms_tenant_id} onChange={(e) => setData('ms_tenant_id', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                        <label className="block text-sm font-medium">
                            Client secret {settings.ms_client_secret_set && <span className="text-emerald-600">(saved — leave blank to keep)</span>}
                            <input type="password" value={data.ms_client_secret} onChange={(e) => setData('ms_client_secret', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                </Card>

                <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                    Save settings
                </button>
            </form>
        </AppShell>
    );
}
