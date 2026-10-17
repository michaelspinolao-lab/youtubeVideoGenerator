<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect('/login')->with('error', 'Configure o login Google no servidor antes de continuar.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $g = Socialite::driver('google')->user();
            if (! ($g->user['email_verified'] ?? $g->user['verified_email'] ?? false)) {
                throw new \RuntimeException('Unverified email');
            }
            if (app()->environment('production') && (! config('video.allowed_email') || strcasecmp($g->email, config('video.allowed_email')) !== 0)) {
                return redirect('/login')->with('error', 'Esta instalação é restrita ao proprietário.');
            }
            $user = User::where('google_id', $g->id)->first();
            if (! $user && User::where('email', $g->email)->exists()) {
                return redirect('/login')->with('error', 'Este e-mail já está associado a outra conta.');
            }
            if (! $user) {
                $user = new User(['google_id' => $g->id, 'password' => Str::random(64)]);
            }
            $user->fill(['name' => $g->name, 'email' => $g->email, 'avatar_url' => $g->avatar]);
            // Sign-in only: do not request or retain YouTube upload access until enabled.
            $user->save();
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        } catch (\Throwable $e) {
            return redirect('/login')->with('error', 'Não foi possível entrar com Google. Tente novamente.');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
