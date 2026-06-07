<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MatchApiController;
use App\Http\Controllers\Api\V1\NotificationApiController;
use App\Http\Controllers\Api\V1\ProfileApiController;
use App\Http\Controllers\Api\V1\TeamApiController;
use App\Http\Controllers\Api\V1\TournamentApiController;
use App\Http\Controllers\Api\V1\WalletApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public Tournament Routes
    Route::get('tournaments', [TournamentApiController::class, 'index'])->name('tournaments.index');
    Route::get('tournaments/{uuid}', [TournamentApiController::class, 'show'])->name('tournaments.show');

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Tournament Registration & Check-in
        Route::post('tournaments/{uuid}/register', [TournamentApiController::class, 'register'])->name('tournaments.register');
        Route::post('tournaments/{uuid}/checkin', [TournamentApiController::class, 'checkin'])->name('tournaments.checkin');

        // Match Routes
        Route::get('matches/{uuid}', [MatchApiController::class, 'show'])->name('matches.show');
        Route::post('matches/{uuid}/result', [MatchApiController::class, 'submitResult'])->name('matches.result');
        Route::post('matches/{uuid}/dispute', [MatchApiController::class, 'dispute'])->name('matches.dispute');

        // Wallet Routes
        Route::get('wallet/balance', [WalletApiController::class, 'balance'])->name('wallet.balance');
        Route::get('wallet/transactions', [WalletApiController::class, 'transactions'])->name('wallet.transactions');
        Route::post('wallet/withdraw', [WalletApiController::class, 'withdraw'])->name('wallet.withdraw');

        // Profile Routes
        Route::get('profile', [ProfileApiController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileApiController::class, 'update'])->name('profile.update');

        // Team Routes
        Route::post('teams', [TeamApiController::class, 'create'])->name('teams.create');
        Route::get('teams/{uuid}', [TeamApiController::class, 'show'])->name('teams.show');
        Route::post('teams/{uuid}/invite', [TeamApiController::class, 'invite'])->name('teams.invite');

        // Notification Routes
        Route::get('notifications', [NotificationApiController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{uuid}/read', [NotificationApiController::class, 'read'])->name('notifications.read');
    });
});

