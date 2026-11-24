<?php

declare(strict_types=1);

namespace App\Livewire\Landing;

use App\Modules\CMS\Services\GuestCompetitionService;
use App\Modules\CMS\Services\LandingPageContentService;
use App\Modules\Community\Models\PlayerReview;
use Livewire\Attributes\Url;
use Livewire\Component;

class LandingPage extends Component
{
    #[Url]
    public string $category = '';

    #[Url(as: 'game_q')]
    public string $gameSearch = '';

    public function selectCategory(string $slug = ''): void
    {
        $this->category = trim($slug);
    }

    public function clearGameFilters(): void
    {
        $this->category = '';
        $this->gameSearch = '';
    }

    public function render(LandingPageContentService $content, GuestCompetitionService $competitions)
    {
        $allRegistrationTournaments = $competitions->registrationTournaments();
        $categoryRegistrationTournaments = $this->category !== ''
            ? $competitions->registrationTournaments($this->category)
            : $allRegistrationTournaments;

        return view('livewire.landing.landing-page', array_merge($content->data($this->category, $this->gameSearch), [
            'publicTournaments' => $competitions->activeTournaments(),
            'heroTournament' => $allRegistrationTournaments->first(),
            'registrationTournaments' => $categoryRegistrationTournaments,
            'playerReviews' => PlayerReview::query()->with('user.profile')->where('status', 'approved')->latest('moderated_at')->limit(6)->get(),
        ]))
            ->layout('components.layouts.landing', [
                'title' => 'GamersRival | Play. Win. Cash.',
            ]);
    }
}
