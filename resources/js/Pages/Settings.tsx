import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface SyncSummary {
    members_received?: number;
    members_created?: number;
    members_updated?: number;
    notes_created?: number;
    notes_updated?: number;
}

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
        wordpress_url: string | null;
        wordpress_username: string | null;
        wordpress_application_password_set: boolean;
        wordpress_last_sync_at: string | null;
        wordpress_last_sync_summary: SyncSummary;
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
        wordpress_url: settings.wordpress_url ?? '',
        wordpress_username: settings.wordpress_username ?? '',
        wordpress_application_password: '',
    });
    const [testingWordPress, setTestingWordPress] = useState(false);
    const [syncingWordPress, setSyncingWordPress] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        put('/settings', { preserveScroll: true });
    }

    function testWordPress() {
        router.post('/settings/wordpress/test', {}, {
            preserveScroll: true,
            onStart: () => setTestingWordPress(true),
            onFinish: () => setTestingWordPress(false),
        });
    }

    function syncWordPress() {
        router.post('/settings/wordpress/sync', {}, {
            preserveScroll: true,
            onStart: () => setSyncingWordPress(true),
            onFinish: () => setSyncingWordPress(false),
        });
    }

    const lastSync = settings.wordpress_last_sync_at
        ? new Date(settings.wordpress_last_sync_at).toLocaleString('en-GB')
        : null;

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

                <Card title="WordPress member sync">
                    <p className="text-sm text-slate-500 mb-3">
                        Pull dates of birth, medical/profile notes, interests, staff notes and end-of-day session notes from the original WordPress Hub. Existing members are matched by name, so they are updated rather than duplicated.
                    </p>
                    <div className="space-y-3">
                        <label className="block text-sm font-medium">
                            WordPress site URL
                            <input
                                type="url"
                                value={data.wordpress_url}
                                onChange={(e) => setData('wordpress_url', e.target.value)}
                                placeholder="https://areterra.co.uk"
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            WordPress username
                            <input
                                value={data.wordpress_username}
                                onChange={(e) => setData('wordpress_username', e.target.value)}
                                placeholder="Your WordPress admin username"
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Application Password {settings.wordpress_application_password_set && <span className="text-emerald-600">(saved - leave blank to keep)</span>}
                            <input
                                type="password"
                                value={data.wordpress_application_password}
                                onChange={(e) => setData('wordpress_application_password', e.target.value)}
                                placeholder="Paste the WordPress Application Password"
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                    </div>

                    <div className="mt-4 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                        Save the settings first, then test the connection. In WordPress, create the password under Users - Profile - Application Passwords and name it "Laravel Hub Sync".
                    </div>

                    {lastSync && (
                        <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            <div className="font-bold">Last sync: {lastSync}</div>
                            <div className="mt-1 text-xs">
                                {settings.wordpress_last_sync_summary.members_received ?? 0} members received,{' '}
                                {settings.wordpress_last_sync_summary.members_created ?? 0} created,{' '}
                                {settings.wordpress_last_sync_summary.members_updated ?? 0} updated,{' '}
                                {settings.wordpress_last_sync_summary.notes_created ?? 0} notes added.
                            </div>
                        </div>
                    )}

                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={testWordPress}
                            disabled={testingWordPress || syncingWordPress}
                            className="rounded-lg border border-brand text-brand font-bold py-3 disabled:opacity-60"
                        >
                            {testingWordPress ? 'Testing...' : 'Test connection'}
                        </button>
                        <button
                            type="button"
                            onClick={syncWordPress}
                            disabled={testingWordPress || syncingWordPress}
                            className="rounded-lg bg-emerald-600 text-white font-bold py-3 disabled:opacity-60"
                        >
                            {syncingWordPress ? 'Syncing...' : 'Sync members now'}
                        </button>
                    </div>
                </Card>

                <Card title="Microsoft sign-in (Azure)">
                    <p className="text-xs text-slate-400 mb-3">
                        From your Azure app registration. Redirect URI: <code className="bg-slate-100 px-1 rounded">{window.location.origin}/ah-ms-callback</code>
                    </p>
                    <p className="text-xs text-amber-600 mb-3">
                        Tenant ID is required alongside Client ID - without it, sign-in stays disabled rather than falling back to accepting any Microsoft account.
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
                            Client secret {settings.ms_client_secret_set && <span className="text-emerald-600">(saved - leave blank to keep)</span>}
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
