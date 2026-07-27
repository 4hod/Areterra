<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        $lastSummary = json_decode(Setting::get('wordpress_last_sync_summary', '{}') ?? '{}', true);

        return Inertia::render('Settings', [
            'settings' => [
                'org_name' => Setting::get('org_name', 'Areterra Hub'),
                'logo_url' => Setting::get('logo_url'),
                'login_photo_url' => Setting::get('login_photo_url'),
                'reply_to' => Setting::get('reply_to', 'team@areterra.co.uk'),
                'banner_text' => Setting::get('banner_text'),
                'ms_client_id' => Setting::get('ms_client_id'),
                'ms_tenant_id' => Setting::get('ms_tenant_id'),
                'ms_client_secret_set' => Setting::get('ms_client_secret') !== null,
                'wordpress_url' => Setting::get('wordpress_url'),
                'wordpress_username' => Setting::get('wordpress_username'),
                'wordpress_application_password_set' => Setting::get('wordpress_application_password') !== null,
                'wordpress_last_sync_at' => Setting::get('wordpress_last_sync_at'),
                'wordpress_last_sync_summary' => is_array($lastSummary) ? $lastSummary : [],
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'login_photo_url' => ['nullable', 'url', 'max:500'],
            'reply_to' => ['required', 'email', 'max:255'],
            'banner_text' => ['nullable', 'string', 'max:500'],
            'ms_client_id' => ['nullable', 'string', 'max:100'],
            'ms_tenant_id' => ['nullable', 'string', 'max:100', 'required_with:ms_client_id'],
            'ms_client_secret' => ['nullable', 'string', 'max:200'],
            'wordpress_url' => ['nullable', 'url', 'max:500'],
            'wordpress_username' => ['nullable', 'string', 'max:100', 'required_with:wordpress_url'],
            'wordpress_application_password' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ([
            'org_name',
            'logo_url',
            'login_photo_url',
            'reply_to',
            'banner_text',
            'ms_client_id',
            'ms_tenant_id',
            'wordpress_url',
            'wordpress_username',
        ] as $key) {
            Setting::set($key, $data[$key] ?? null);
        }

        // Secrets are write-only: blank means keep the existing value.
        if (! empty($data['ms_client_secret'])) {
            Setting::set('ms_client_secret', encrypt($data['ms_client_secret']));
        }

        if (! empty($data['wordpress_application_password'])) {
            Setting::set('wordpress_application_password', encrypt($data['wordpress_application_password']));
        }

        return back()->with('success', 'Settings saved.');
    }
}
