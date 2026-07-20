import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors } = useForm({ password: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/confirm-password');
    }

    return (
        <>
            <Head title="Confirm password" />
            <div className="min-h-screen flex items-center justify-center bg-brand-dark p-4">
                <form onSubmit={submit} className="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6 space-y-4">
                    <div className="text-3xl text-center">🛡️</div>
                    <h1 className="text-lg font-extrabold text-brand-dark text-center">Restricted section</h1>
                    <p className="text-sm text-slate-500 text-center">
                        Please re-enter your password to continue.
                    </p>
                    <div>
                        <label htmlFor="password" className="block text-sm font-medium mb-1">Password</label>
                        <input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3"
                            autoFocus
                            required
                        />
                        {errors.password && <p className="text-red-600 text-sm mt-1">{errors.password}</p>}
                    </div>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Confirm
                    </button>
                </form>
            </div>
        </>
    );
}
