<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\WithdrawalStatus;
use Spatie\Activitylog\Models\Activity;

class AdminDashboard extends AdminComponent
{
    public function render()
    {
        $stats = [
            'total_users' => User::count(),
            'pending_kyc' => KycSubmission::where('status', KycStatus::SUBMITTED->value)->count(),
            'pending_withdrawals' => Withdrawal::where('status', WithdrawalStatus::PENDING->value)->count(),
            'open_disputes' => MatchDispute::where('status', DisputeStatus::OPEN->value)->count(),

            'active_tournaments' => Tournament::whereIn('status', [
                TournamentStatus::PUBLISHED->value,
                TournamentStatus::REGISTRATION_OPEN->value,
                TournamentStatus::CHECKIN_OPEN->value,
                TournamentStatus::ONGOING->value,
            ])->count(),
            'completed_tournaments' => Tournament::where('status', TournamentStatus::COMPLETED->value)->count(),

            'active_matches' => GameMatch::whereIn('status', [
                MatchStatus::READY->value,
                MatchStatus::IN_PROGRESS->value,
                MatchStatus::WAITING_FOR_CONFIRMATION->value,
                MatchStatus::RESULT_SUBMITTED->value,
            ])->count(),
            'completed_matches' => GameMatch::whereIn('status', [
                MatchStatus::COMPLETED->value,
                MatchStatus::FORFEITED->value,
            ])->count(),

            'total_escrow' => Wallet::sum('cached_balance'),
        ];

        $recentActivities = Activity::orderBy('created_at', 'desc')
            ->with('causer')
            ->take(10)
            ->get();

        return view('livewire.admin.admin-dashboard', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Dashboard Overview',
        ]);
    }
}
