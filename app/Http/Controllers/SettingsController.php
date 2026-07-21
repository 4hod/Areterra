<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings', [
            'settings' => [
                'org_name' => Setting::get('org_name', 'Areterra Hub'),
                'logo_url' => Setting::get('logo_url'),
                'reply_to' => Setting::get('reply_to', 'team@areterra.co.uk'),
                'banner_text' => Setting::get('banner_text'),
                'ms_client_id' => Setting::get('ms_client_id'),
                'ms_tenant_id' => Setting::get('ms_tenant_id'),
                'ms_client_secret_set' => Setting::get('ms_client_secret') !== null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'reply_to' => ['required', 'email', 'max:255'],
            'banner_text' => ['nullable', 'string', 'max:500'],
            'ms_client_id' => ['nullable', 'string', 'max:100'],
            'ms_tenant_id' => ['nullable', 'string', 'max:100', 'required_with:ms_client_id'],
            'ms_client_secret' => ['nullable', 'string', 'max:200'],
        ]);

        foreach (['org_name', 'logo_url', 'reply_to', 'banner_text', 'ms_client_id', 'ms_tenant_id'] as $key) {
            Setting::set($key, $data[$key] ?? null);
        }

        // Secret is write-only: blank means "keep the existing one".
        if (! empty($data['ms_client_secret'])) {
            Setting::set('ms_client_secret', encrypt($data['ms_client_secret']));
        }

        return back()->with('success', 'Settings saved.');
    }
}
