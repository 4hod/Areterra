import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useRef } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import { SharedProps } from '../types';
import ModuleHero from '../components/ModuleHero';

interface Profile {
    name: string;
    job_title: string | null;
    email: string;
    phone: string | null;
    bio: string | null;
    photo_path: string | null;
}

export default function Account({ profile }: { profile: Profile }) {
    const { auth } = usePage<SharedProps>().props;
    const photoInput = useRef<HTMLInputElement>(null);

    const profileForm = useForm({
        name: profile.name,
        job_title: profile.job_title ?? '',
        phone: profile.phone ?? '',
        bio: profile.bio ?? '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submitProfile(e: FormEvent) {
        e.preventDefault();
        profileForm.put('/account/profile');
    }

    function submitPassword(e: FormEvent) {
        e.preventDefault();
        passwordForm.put('/account/password', { onSuccess: () => passwordForm.reset() });
    }

    function onPhotoSelected(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        router.post('/account/photo', { photo: file }, { forceFormData: true });
    }

    return (
        <AppShell title="My Account">
            <Head title="My Account" />
            <ModuleHero eyebrow="Your workspace" title="Account" description="Manage your profile, security and personal workspace preferences." icon="👤" tone="slate" />

            <Card className="mb-4">
                <div className="flex items-center gap-4">
                    <div className="relative shrink-0">
                        {profile.photo_path ? (
                            <img src={profile.photo_path} alt={profile.name} className="h-16 w-16 rounded-full object-cover" />
                        ) : (
                            <div className="h-16 w-16 rounded-full bg-brand/10 text-brand font-bold text-xl flex items-center justify-center">
                                {profile.name.charAt(0)}
                            </div>
                        )}
                        <button
                            onClick={() => photoInput.current?.click()}
                            aria-label="Change photo"
                            className="absolute -bottom-1 -right-1 h-6 w-6 rounded-full bg-brand text-white text-xs flex items-center justify-center border-2 border-white"
                        >
                            📷
                        </button>
                        <input ref={photoInput} type="file" accept="image/*" className="hidden" onChange={onPhotoSelected} />
                    </div>
                    <div className="min-w-0">
                        <div className="font-bold text-brand-dark text-lg">{profile.name}</div>
                        <p className="text-sm text-slate-500 capitalize">
                            Role: <b className="text-brand-dark">{auth.user?.role?.replace('_', ' ')}</b>
                        </p>
                        {profile.photo_path && (
                            <button
                                onClick={() => router.delete('/account/photo')}
                                className="text-xs font-semibold text-red-500 mt-1"
                            >
                                Remove photo
                            </button>
                        )}
                    </div>
                </div>
            </Card>

            <form onSubmit={submitProfile}>
                <Card title="Profile" className="mb-4">
                    <div className="space-y-3">
                        <label className="block text-sm font-medium">
                            Display name
                            <input
                                value={profileForm.data.name}
                                onChange={(e) => profileForm.setData('name', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                required
                            />
                            {profileForm.errors.name && <span className="text-red-600 text-xs">{profileForm.errors.name}</span>}
                        </label>
                        <label className="block text-sm font-medium">
                            Job title
                            <input
                                value={profileForm.data.job_title}
                                onChange={(e) => profileForm.setData('job_title', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Email
                            <input value={profile.email} disabled className="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 text-slate-400" />
                            <span className="text-xs text-slate-400">Contact an administrator to change your email.</span>
                        </label>
                        <label className="block text-sm font-medium">
                            Phone
                            <input
                                value={profileForm.data.phone}
                                onChange={(e) => profileForm.setData('phone', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Bio
                            <textarea
                                value={profileForm.data.bio}
                                onChange={(e) => profileForm.setData('bio', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                                rows={3}
                            />
                        </label>
                        <button
                            type="submit"
                            disabled={profileForm.processing}
                            className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60"
                        >
                            Save profile
                        </button>
                    </div>
                </Card>
            </form>

            <form onSubmit={submitPassword}>
                <Card title="Change password">
                    <div className="space-y-3">
                        <label className="block text-sm font-medium">
                            Current password
                            <input
                                type="password"
                                value={passwordForm.data.current_password}
                                onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                autoComplete="current-password"
                                required
                            />
                            {passwordForm.errors.current_password && (
                                <span className="text-red-600 text-xs">{passwordForm.errors.current_password}</span>
                            )}
                        </label>
                        <label className="block text-sm font-medium">
                            New password (10+ characters)
                            <input
                                type="password"
                                value={passwordForm.data.password}
                                onChange={(e) => passwordForm.setData('password', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                autoComplete="new-password"
                                required
                            />
                            {passwordForm.errors.password && <span className="text-red-600 text-xs">{passwordForm.errors.password}</span>}
                            {passwordForm.data.password.length > 0 && (() => {
                                const pw = passwordForm.data.password;
                                let score = 0;
                                if (pw.length >= 10) score++;
                                if (pw.length >= 14) score++;
                                if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
                                if (/[0-9]/.test(pw)) score++;
                                if (/[^A-Za-z0-9]/.test(pw)) score++;
                                const levels = [
                                    { label: 'Weak', color: 'bg-status-red' },
                                    { label: 'Weak', color: 'bg-status-red' },
                                    { label: 'Fair', color: 'bg-status-amber' },
                                    { label: 'Good', color: 'bg-status-amber' },
                                    { label: 'Strong', color: 'bg-status-green' },
                                    { label: 'Strong', color: 'bg-status-green' },
                                ];
                                const level = levels[score];
                                return (
                                    <div className="mt-1.5">
                                        <div className="flex gap-1">
                                            {[0, 1, 2, 3, 4].map((i) => (
                                                <div key={i} className={`h-1 flex-1 rounded-full ${i < score ? level.color : 'bg-black/[0.08]'}`} />
                                            ))}
                                        </div>
                                        <span className="text-xs text-ink/40">{level.label}</span>
                                    </div>
                                );
                            })()}
                        </label>
                        <label className="block text-sm font-medium">
                            Confirm new password
                            <input
                                type="password"
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                autoComplete="new-password"
                                required
                            />
                        </label>
                        <button
                            type="submit"
                            disabled={passwordForm.processing}
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
