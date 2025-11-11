<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\FileViewController;

// GOOGLE AUTHENTICATION ROUTES
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');

// SECURE FILE STREAMING ROUTE
Route::middleware('auth')->group(function () {
    Route::get(
        '/submissions/{submission}/file/{fileKey}',
        [FileViewController::class, 'streamFile']
    )->name('submission.file.view');
});

// HOMEPAGE REDIRECT TO INSTRUCTOR PANEL
Route::get('/', function () {
    return redirect()->route('filament.instructor.auth.login');
});
