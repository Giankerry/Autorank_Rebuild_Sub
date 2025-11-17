<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class GoogleSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.shared.google-settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Google Drive Settings';

    protected static ?string $slug = 'google-settings';

    public bool $hasDriveAccess = false;

    /**
     * Get the data for the view.
     */
    public function mount(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user) {
            $this->hasDriveAccess = $user->hasDriveScope();
        }
    }

    /**
     * Action to redirect to Google for re-authentication.
     */
    public function redirectToGoogle()
    {
        Session::put('google_auth_redirect', 'settings');

        $redirectResponse = app(\App\Http\Controllers\Auth\GoogleAuthController::class)->redirectToGoogle();

        return $this->redirect($redirectResponse->getTargetUrl());
    }

    public function getHeading(): string
    {
        return 'Google Integration Settings';
    }
}
