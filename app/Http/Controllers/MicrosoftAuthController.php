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
        $clientId = config('services.microsoft.client_id') ?? Setting::get('ms_client_id');
        $tenant = config('services.microsoft.tenant_id') ?? Setting::get('ms_tenant_id');
        $storedSecret = Setting::get('ms_client_secret');

        try {
            $secret = config('services.microsoft.client_secret') ?? ($storedSecret ? decrypt($storedSecret) : null);
        } catch (\Throwable) {
            $secret = null;
        }

        // Require an explicit tenant — accepting sign-ins via the 'common'
        // endpoint would let any Microsoft/Entra tenant (or personal account)
        // attempt to authenticate, relying solely on email-matching to keep
        // outsiders out.
        return self::validClientId($clientId)
            && self::validTenant($tenant)
            && is_string($secret)
            && strlen($secret) >= 20
            && ! preg_match('/^[*\x{2022}]+$/u', $secret);
    }

    public function redirect(Request $request)
    {
        if (! self::configured()) {
            return redirect('/login')->with('error', 'Microsoft sign-in is not set up yet.');
        }

        $state = Str::random(40);
        $request->session()->put('ms_oauth_state', $state);
        $confirmingPassword = $request->boolean('confirm');
        $request->session()->put('ms_password_confirmation', $confirmingPassword);

        $params = http_build_query([
            'client_id' => $this->clientId(),
            'response_type' => 'code',
            'redirect_uri' => route('microsoft.callback'),
            'scope' => 'openid profile email User.Read',
            'state' => $state,
            ...($confirmingPassword ? ['prompt' => 'login'] : []),
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

        Auth::login($user);
        $request->session()->regenerate();
        // A successful tenant-restricted Microsoft sign-in is a fresh
        // credential check. This keeps password.confirm compatible with the
        // SSO-only rollout, where staff do not have a local Hub password.
        $request->session()->passwordConfirmed();
        $request->session()->forget('ms_password_confirmation');

        return redirect()->intended('/');
    }

    private function clientId(): ?string
    {
        return config('services.microsoft.client_id') ?? Setting::get('ms_client_id');
    }

    private function clientSecret(): ?string
    {
        if ($configured = config('services.microsoft.client_secret')) {
            return $configured;
        }

        $stored = Setting::get('ms_client_secret');

        return $stored ? decrypt($stored) : null;
    }

    private function tenant(): string
    {
        // configured() guarantees one of these is set before redirect()/callback()
        // are ever reached, so no 'common' fallback here.
        return config('services.microsoft.tenant_id') ?? Setting::get('ms_tenant_id');
    }

    public static function validClientId(mixed $clientId): bool
    {
        return is_string($clientId) && Str::isUuid($clientId);
    }

    public static function validTenant(mixed $tenant): bool
    {
        if (! is_string($tenant) || $tenant === '') {
            return false;
        }

        if (in_array(mb_strtolower($tenant), ['common', 'organizations', 'consumers'], true)) {
            return false;
        }

        return Str::isUuid($tenant)
            || filter_var($tenant, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
