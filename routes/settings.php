<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    // ── Profile ──────────────────────────────────────────────────────────────
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Profile picture — named consistently with Claude.md documentation
    Route::post('settings/profile/picture', [ProfileController::class, 'updatePicture'])
        ->name('profile.update-picture');
    Route::delete('settings/profile/picture', [ProfileController::class, 'removePicture'])
        ->name('profile.remove-picture');

    // Account deletion
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Password ─────────────────────────────────────────────────────────────
    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // ── Appearance ───────────────────────────────────────────────────────────
    // Canonical location. The duplicate in routes/web.php has been removed.
    Route::get('settings/appearance', fn () => Inertia::render('settings/Appearance'))
        ->name('appearance');
});