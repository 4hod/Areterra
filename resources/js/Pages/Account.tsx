import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import { SharedProps } from '../types';

export default function Account() {
    const { auth } = usePage<SharedProps>().props;
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        put('/account/password', { onSuccess: () => reset() });
    }

    return (
        <AppShell title="My Account">
            <Head title="My Account" />

            <Card title={auth.user?.name ?? ''} className="mb-4">
                <p className="text-sm text-slate-500 capitalize">
                    Role: <b className="text-brand-dark">{auth.user?.role?.replace('_', ' ')}</b>
                </p>
            </Card>

            <form onSubmit={submit}>
                <Card title="Change password">
                    <div className="space-y-3">
                        <label className="block text-sm font-medium">
                            Current password
                            <input
                                type="password"
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                autoComplete="current-password"
                                required
                            />
                            {errors.current_password && <span className="text-red-600 text-xs">{errors.current_password}</span>}
                        </label>
                        <label className="block text-sm font-medium">
                            New password (10+ characters)
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                autoComplete="new-password"
                                required
                            />
                            {errors.password && <span className="text-red-600 text-xs">{errors.password}</span>}
                        </label>
                        <label className="block text-sm font-medium">
                            Confirm new password
                            <input
                                type="password"
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                autoComplete="new-password"
                                required
                            />
                        </label>
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                        >
                            Change password
                        </button>
                    </div>
                </Card>
            </form>
        </AppShell>
    );
}
