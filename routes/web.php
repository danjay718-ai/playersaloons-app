<?php

declare(strict_types=1);

use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Admin\AdminProfile;
use App\Livewire\Admin\AuditLogAdmin;
use App\Livewire\Admin\BroadcastNotificationAdmin;
use App\Livewire\Admin\CmsAdmin;
use App\Livewire\Admin\KycAdmin;
use App\Livewire\Admin\MatchAdmin;
use App\Livewire\Admin\StaffActivityDashboard;
use App\Livewire\Admin\TournamentAdmin;
use App\Livewire\Admin\TournamentForm;
use App\Livewire\Admin\UserAdmin;
use App\Livewire\Admin\WithdrawalAdmin;
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

Route::get('/tournaments', \App\Livewire\Tournament\PublicTournamentList::class);

// Guest only routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
    Route::get('/reset-password', PasswordReset::class)->name('password.request');
});

// Authenticated only routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', PlayerDashboard::class)->name('dashboard');
    Route::get('/my-tournaments', \App\Livewire\Tournament\MyTournamentsList::class)->name('my-tournaments');
    Route::get('/tournaments/browse', \App\Livewire\Tournament\PlayerTournamentList::class)->name('tournaments.browse');
    Route::get('/head-to-head', \App\Livewire\Match\HeadToHeadList::class)->name('head-to-head');
    Route::get('/leaderboards', \App\Livewire\Match\LeaderboardList::class)->name('leaderboards');
    Route::get('/streams', \App\Livewire\Stream\StreamList::class)->name('streams');
    Route::get('/chat', \App\Livewire\Community\GlobalChat::class)->name('chat');
    Route::get('/tournaments/{uuid}/view', \App\Livewire\Tournament\TournamentDetail::class)->name('tournaments.view');
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
        Route::get('/', AdminDashboard::class);
        Route::get('/profile', AdminProfile::class);
        Route::get('/tournaments', TournamentAdmin::class)->name('admin.tournaments');
        Route::get('/tournaments/create', TournamentForm::class)->name('admin.tournaments.create');
        Route::get('/tournaments/{id}/edit', TournamentForm::class)->name('admin.tournaments.edit');
        Route::get('/matches', MatchAdmin::class);
        Route::get('/kyc', KycAdmin::class);
        Route::get('/kyc/document/{path}', function (string $path) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (! $user || ! $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'KYC_REVIEWER'])) {
                abort(403, 'Unauthorized access to KYC document.');
            }

            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            if (! $disk->exists($path)) {
                abort(404, 'KYC document not found.');
            }

            return $disk->response($path);
        })->where('path', '.*')->name('admin.kyc.document');
        Route::get('/withdrawals', WithdrawalAdmin::class);
        Route::get('/users', UserAdmin::class);
        Route::get('/audit-logs', AuditLogAdmin::class);
        Route::get('/cms', CmsAdmin::class);
        Route::get('/notifications', BroadcastNotificationAdmin::class)->name('admin.notifications');
        Route::get('/staff-activity', StaffActivityDashboard::class)->name('admin.staff-activity');
    });
});
