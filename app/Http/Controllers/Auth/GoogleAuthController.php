<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        /** @var \Laravel\Socialite\Two\GoogleProvider $google */
        $google = Socialite::driver('google');

        return $google->scopes([Drive::DRIVE_FILE])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent'
            ])
            ->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('filament.instructor.auth.login')
                ->withErrors(['google_error' => 'Login with Google failed. Please make sure to grant permission to access your Google account.']);
        }

        $user = User::updateOrCreate([
            'google_id' => $googleUser->id,
        ], [
            'name' => $googleUser->name,
            'email' => $googleUser->email,
            'google_token' => $googleUser->token,
            'google_refresh_token' => $googleUser->refreshToken ?? User::where('google_id', $googleUser->id)->first()?->google_refresh_token,
            'avatar_url' => $googleUser->getAvatar(),
        ]);

        if ($user->wasRecentlyCreated) {
            $user->assignRole('Instructor');
        }

        Auth::login($user);

        $request->session()->regenerate();

        // Redirect users based on their role
        if ($user->hasRole(['Admin', 'Super Admin'])) {
            return redirect()->intended('/admin');
        }

        if ($user->hasRole('Validator')) {
            return redirect()->intended('/validator');
        }

        // Default redirect for Instructors
        return redirect()->intended('/instructor');
    }
}
