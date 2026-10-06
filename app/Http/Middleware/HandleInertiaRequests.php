<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'capabilities' => $user->capabilities(),
                ] : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'import_preview' => $request->session()->get('import_preview'),
                'archive_import_report' => $request->session()->get('archive_import_report'),
            ],
            'branding' => [
                'logoUrl' => \App\Models\Setting::get('logo_url'),
                'orgName' => \App\Models\Setting::get('org_name') ?? 'Areterra Hub',
            ],
            'unreadNotifications' => $user ? $user->unreadNotifications()->count() : 0,
            'pushConfigured' => filled(config('webpush.vapid.public_key')),
        ];
    }
}
