import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Props {
    prefs: {
        push_enabled: boolean;
        email_enabled: boolean;
        categories: Record<string, boolean>;
    };
    vapidPublicKey: string | null;
    hasSubscription: boolean;
}

const CATEGORY_LABELS: Record<string, string> = {
    announcements: '📢 Announcements',
    concerns: '⚠️ Welfare & safeguarding concerns',
    leave: '🌴 Leave requests',
    reminders: '⏰ Daily reminders',
};

function urlBase64ToUint8Array(base64: string) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

function Toggle({ on, onChange }: { on: boolean; onChange: (v: boolean) => void }) {
    return (
        <button
            type="button"
            onClick={() => onChange(!on)}
            aria-pressed={on}
            className={`w-12 h-7 rounded-full relative transition ${on ? 'bg-brand' : 'bg-slate-300'}`}
        >
            <span className={`absolute top-1 h-5 w-5 rounded-full bg-white transition-all ${on ? 'left-6' : 'left-1'}`} />
        </button>
    );
}

export default function NotificationPrefs({ prefs, vapidPublicKey, hasSubscription }: Props) {
    const { data, setData, put, processing } = useForm(prefs);
    const [subscribed, setSubscribed] = useState(hasSubscription);
    const [subscribing, setSubscribing] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        put('/notifications');
    }

    async function enablePush() {
        if (!vapidPublicKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
        setSubscribing(true);
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });
            const json = subscription.toJSON();
            await fetch('/push-subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                    ),
                },
                body: JSON.stringify({ endpoint: json.endpoint, keys: json.keys }),
            });
            setSubscribed(true);
        } finally {
            setSubscribing(false);
        }
    }

    return (
        <AppShell title="Notifications">
            <Head title="Notifications" />

            <Card title="This device" className="mb-4">
                {subscribed ? (
                    <p className="text-sm text-emerald-700 font-semibold">✓ Push notifications are enabled on this device.</p>
                ) : vapidPublicKey ? (
                    <button
                        onClick={enablePush}
                        disabled={subscribing}
                        className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 disabled:opacity-60"
                    >
                        🔔 Enable push on this device
                    </button>
                ) : (
                    <p className="text-sm text-slate-400">Push is not configured on the server (VAPID keys missing).</p>
                )}
                <button
                    onClick={() => router.post('/notifications/test')}
                    className="mt-3 block text-sm font-semibold text-brand"
                >
                    Send a test notification
                </button>
            </Card>

            <form onSubmit={submit}>
                <Card title="Preferences">
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <span className="font-semibold text-sm">Push notifications</span>
                            <Toggle on={data.push_enabled} onChange={(v) => setData('push_enabled', v)} />
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="font-semibold text-sm">Email notifications</span>
                            <Toggle on={data.email_enabled} onChange={(v) => setData('email_enabled', v)} />
                        </div>
                        <hr className="border-slate-100" />
                        {Object.entries(data.categories).map(([key, on]) => (
                            <div key={key} className="flex items-center justify-between">
                                <span className="text-sm">{CATEGORY_LABELS[key] ?? key}</span>
                                <Toggle on={on} onChange={(v) => setData('categories', { ...data.categories, [key]: v })} />
                            </div>
                        ))}
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-4 w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                    >
                        Save preferences
                    </button>
                </Card>
            </form>
        </AppShell>
    );
}
