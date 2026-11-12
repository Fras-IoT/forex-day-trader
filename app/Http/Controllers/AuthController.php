<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        User::ensureDefaultAdmin();

        return view('auth.login');
    }

    public function login(Request $request)
    {
        User::ensureDefaultAdmin();

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();
        if ($user && $user->is_active === false) {
            return back()->withErrors(['email' => 'Akun ini sedang dinonaktifkan oleh admin.'])->onlyInput('email');
        }

        if (Auth::attempt(['email' => strtolower(trim($credentials['email'])), 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email atau password tidak sesuai.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Anda telah keluar.');
    }
}

