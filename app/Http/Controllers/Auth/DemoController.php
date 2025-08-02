<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DemoController extends Controller
{
    public function loginDemo(): RedirectResponse
    {
        // Create or find a demo user
        $user = User::firstOrCreate([
            'email' => 'demo@youtubegen.local'
        ], [
            'name' => 'Demo User',
            'email' => 'demo@youtubegen.local',
            'password' => Hash::make('demo123'),
            'email_verified_at' => now(),
            'google_id' => null,
            'avatar_url' => '/placeholder-user.jpg',
        ]);

        // Login the demo user
        Auth::login($user, true);

        return redirect()->route('dashboard')->with('success', 'Welcome to the demo! You are now logged in as a demo user.');
    }
}
