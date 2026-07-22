<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function show()
    {
        return Inertia::render('Login', [
            'ssoConfigured' => MicrosoftAuthController::configured(),
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
                'photo_path' => $user->photo_path,
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

        $path = $request->file('photo')->store('staff-photos', 'public');
        $request->user()->update(['photo_path' => '/storage/'.$path]);

        return back()->with('success', 'Photo updated.');
    }

    public function removePhoto(Request $request)
    {
        $request->user()->update(['photo_path' => null]);

        return back()->with('success', 'Photo removed.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $request->user()->update(['password' => $request->string('password')]);

        return back()->with('success', 'Password changed.');
    }

    public function confirmShow()
    {
        return Inertia::render('ConfirmPassword');
    }

    public function confirm(Request $request)
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->session()->passwordConfirmed();

        return redirect()->intended('/');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those details don\'t match our records.',
            ]);
        }

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
