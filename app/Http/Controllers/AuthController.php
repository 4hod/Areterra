<?php

namespace App\Http\Controllers;

use App\Support\PrivateMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function show()
    {
        return Inertia::render('Login', [
            'ssoConfigured' => MicrosoftAuthController::configured(),
            'localPasswordEnabled' => ! config('security.require_microsoft_sso'),
            'loginPhotoUrl' => \App\Models\Setting::get('login_photo_url'),
            'logoUrl' => \App\Models\Setting::get('logo_url'),
        ]);
    }

    public function account(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Account', [
            'profile' => [
                'name' => $user->name,
                'job_title' => $user->job_title,
                'email' => $user->email,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'photo_path' => PrivateMedia::staffPhotoUrl($user),
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePhoto(Request $request)
    {
        $request->validate(['photo' => ['required', 'image', 'max:8192']]);

        $user = $request->user();
        $oldPath = PrivateMedia::path($user->photo_path);
        $path = $request->file('photo')->store('staff-photos', 'local');
        $user->update(['photo_path' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Photo updated.');
    }

    public function removePhoto(Request $request)
    {
        $user = $request->user();
        $oldPath = PrivateMedia::path($user->photo_path);
        $user->update(['photo_path' => null]);
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('success', 'Photo removed.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:128', 'current_password'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        Auth::logoutOtherDevices($data['current_password']);
        $request->user()->update(['password' => $data['password']]);
        $sessionTable = (string) config('session.table', 'sessions');
        if (config('session.driver') === 'database' && Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        $request->session()->regenerate();

        return back()->with('success', 'Password changed.');
    }

    public function confirmShow()
    {
        return Inertia::render('ConfirmPassword');
    }

    public function confirm(Request $request)
    {
        $request->validate(['password' => ['required', 'string', 'max:128', 'current_password']]);

        $request->session()->passwordConfirmed();

        return redirect()->intended('/');
    }

    public function login(Request $request)
    {
        if (config('security.require_microsoft_sso')) {
            throw ValidationException::withMessages([
                'email' => 'Password sign-in is disabled. Use your organisation Microsoft account.',
            ]);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'max:128'],
        ]);

        $accountKey = 'login-account:'.hash('sha256', mb_strtolower($credentials['email']));

        if (RateLimiter::tooManyAttempts($accountKey, 10)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Please wait 15 minutes and try again.',
            ]);
        }

        if (! Auth::attempt($credentials, remember: false)) {
            RateLimiter::hit($accountKey, 15 * 60);
            throw ValidationException::withMessages([
                'email' => 'Those details don\'t match our records.',
            ]);
        }

        RateLimiter::clear($accountKey);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
