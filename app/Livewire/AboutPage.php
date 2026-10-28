<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Services\AboutContentFormatter;
use App\Modules\CMS\Services\GuestCompetitionService;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Operations\Models\SystemSetting;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AboutPage extends Component
{
    public function render(GuestCompetitionService $competitions)
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['about.title', 'about.subtitle', 'about.body'])
            ->pluck('value', 'key');

        $title = $settings['about.title'] ?? 'About GamersRival';
        $subtitle = $settings['about.subtitle'] ?? 'Our mission is to revolutionize competitive gaming.';
        $body = $settings['about.body'] ?? '<p>Welcome to GamersRival.</p>';

        $games = Game::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('slug')
            ->limit(12)
            ->get();

        $openTournaments = $competitions->registrationTournaments();
        $winSummary = $competitions->winSummary();

        $activePlayers = DB::table('users')
            ->whereNull('deleted_at')
            ->where('status', UserStatus::ACTIVE->value)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->whereIn('roles.name', ['PLAYER', 'TEAM_CAPTAIN'])
                    ->whereNull('roles.deleted_at');
            })
            ->count();

        $matchesSettled = GameMatch::query()
            ->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->count()
            + HeadToHeadMatch::query()
                ->where('status', HeadToHeadMatchStatus::COMPLETED->value)
                ->count();

        $stats = [
            'players' => $activePlayers,
            'matches' => $matchesSettled,
        ];

        return view('livewire.about-page', [
            'title' => $title,
            'subtitle' => $subtitle,
            'body' => app(AboutContentFormatter::class)->format($body),
            'stats' => $stats,
            'winSummary' => $winSummary,
            'games' => $games,
            'openTournaments' => $openTournaments,
        ])->layout('components.layouts.landing', ['title' => 'About Us - GamersRival']);
    }
}
