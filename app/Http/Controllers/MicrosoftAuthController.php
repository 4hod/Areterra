<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// Azure OAuth2 sign-in: authorize → token → Microsoft Graph /me → map by email.
// Configured from Hub Settings (falls back to services.microsoft env config).
class MicrosoftAuthController extends Controller
{
    public static function configured(): bool
    {
        $clientId = Setting::get('ms_client_id') ?? config('services.microsoft.client_id');
        $tenant = Setting::get('ms_tenant_id') ?? config('services.microsoft.tenant_id');

        // Require an explicit tenant — accepting sign-ins via the 'common'
        // endpoint would let any Microsoft/Entra tenant (or personal account)
        // attempt to authenticate, relying solely on email-matching to keep
        // outsiders out.
        return (bool) ($clientId && $tenant);
    }

    public function redirect(Request $request)
    {
        if (! self::configured()) {
            return redirect('/login')->with('error', 'Microsoft sign-in is not set up yet.');
        }

        $state = Str::random(40);
        $request->session()->put('ms_oauth_state', $state);

        $params = http_build_query([
            'client_id' => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri' => route('microsoft.callback'),
            'scope' => 'openid profile email User.Read',
            'state' => $state,
        ]);

        return redirect("https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/authorize?{$params}");
    }

    public function callback(Request $request)
    {
        if (! self::configured()) {
            return redirect('/login')->with('error', 'Microsoft sign-in is not set up yet.');
        }

        // Friendly-error redirects — never a white error screen (SPEC.md §23).
        if ($request->input('state') !== $request->session()->pull('ms_oauth_state')) {
            return redirect('/login')->with('error', 'Microsoft sign-in expired — please try again.');
        }

        if (! $request->filled('code')) {
            return redirect('/login')->with('error', 'Microsoft sign-in was cancelled.');
        }

        try {
            $token = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenant()}/oauth2/v2.0/token",
                [
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'code' => $request->input('code'),
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => route('microsoft.callback'),
                ],
            )->throw()->json('access_token');

            $profile = Http::withToken($token)
                ->get('https://graph.microsoft.com/v1.0/me')
                ->throw()
                ->json();
        } catch (\Throwable) {
            return redirect('/login')->with('error', 'Microsoft sign-in failed — please try again or use your password.');
        }

        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;
        $user = $email ? User::whereRaw('lower(email) = ?', [mb_strtolower($email)])->first() : null;

        if (! $user) {
            return redirect('/login')->with('error', 'No Hub account matches that Microsoft account — ask a manager to set one up.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    private function clientId(): ?string
    {
        return Setting::get('ms_client_id') ?? config('services.microsoft.client_id');
    }

    private function clientSecret(): ?string
    {
        $stored = Setting::get('ms_client_secret');

        return $stored ? decrypt($stored) : config('services.microsoft.client_secret');
    }

    private function tenant(): string
    {
        // configured() guarantees one of these is set before redirect()/callback()
        // are ever reached, so no 'common' fallback here.
        return Setting::get('ms_tenant_id') ?? config('services.microsoft.tenant_id');
    }
}
