<?php

declare(strict_types=1);

use App\Livewire\Auth\EmailVerification;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PasswordReset;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard\PlayerDashboard;
use App\Livewire\Match\MatchDetail;
use App\Livewire\Profile\ProfileDashboard;
use App\Livewire\Team\TeamDashboard;
use App\Livewire\Tournament\TournamentDetail;
use App\Livewire\Tournament\TournamentList;
use App\Livewire\Wallet\WalletDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('welcome');
});

Route::get('/tournaments', TournamentList::class);
Route::get('/tournaments/{uuid}', TournamentDetail::class);

// Guest only routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/reset-password', PasswordReset::class)->name('password.request');
});

// Authenticated only routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', PlayerDashboard::class)->name('dashboard');
    Route::get('/matches/{uuid}', MatchDetail::class);
    Route::get('/wallet', WalletDashboard::class);
    Route::get('/profile', ProfileDashboard::class);
    Route::get('/teams', TeamDashboard::class);
    Route::get('/verify-email', EmailVerification::class)->name('verification.notice');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    })->name('logout');

    // Admin Control Panel
    Route::prefix('admin')->group(function () {
        Route::get('/', \App\Livewire\Admin\AdminDashboard::class);
        Route::get('/tournaments', \App\Livewire\Admin\TournamentAdmin::class);
        Route::get('/matches', \App\Livewire\Admin\MatchAdmin::class);
        Route::get('/kyc', \App\Livewire\Admin\KycAdmin::class);
        Route::get('/withdrawals', \App\Livewire\Admin\WithdrawalAdmin::class);
        Route::get('/users', \App\Livewire\Admin\UserAdmin::class);
        Route::get('/audit-logs', \App\Livewire\Admin\AuditLogAdmin::class);
        Route::get('/cms', \App\Livewire\Admin\CmsAdmin::class);
    });
});

