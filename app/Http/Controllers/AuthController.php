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
        ]);
    }

    public function account()
    {
        return Inertia::render('Account');
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
