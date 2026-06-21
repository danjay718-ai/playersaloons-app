<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeadToHeadDuelPrompt extends Component
{
    public function dismiss(string $type, int $id): void
    {
        if (! in_array($type, ['match', 'challenge'], true)) {
            return;
        }

        $key = $type === 'match' ? 'h2h_dismissed_match_prompts' : 'h2h_dismissed_challenge_prompts';
        $dismissed = session($key, []);
        $dismissed[] = $id;

        session()->put($key, array_values(array_unique($dismissed)));
    }

    public function render(): View
    {
        return view('livewire.match.head-to-head-duel-prompt', [
            'prompt' => $this->prompt(),
        ]);
    }

    /**
     * @return array{type:string,id:int,title:string,message:string,game:string,stake:string}|null
     */
    private function prompt(): ?array
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $dismissedMatches = session('h2h_dismissed_match_prompts', []);

        $match = HeadToHeadMatch::query()
            ->with(['creator', 'opponent', 'game.translations'])
            ->whereIn('status', [
                HeadToHeadMatchStatus::IN_PROGRESS,
                HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION,
            ])
            ->where(function ($query) use ($user) {
                $query->where('creator_user_id', $user->getKey())
                    ->orWhere('opponent_user_id', $user->getKey());
            })
            ->whereNotIn('id', $dismissedMatches)
            ->latest('started_at')
            ->first();

        if ($match) {
            $gameName = $match->game->translations->where('locale', 'en')->first()?->name ?? $match->game->slug;
            $opponent = $match->creator_user_id === $user->getKey() ? $match->opponent : $match->creator;
            $title = $match->creator_user_id === $user->getKey() ? 'Your duel was accepted' : 'Duel invitation matched';

            return [
                'type' => 'match',
                'id' => (int) $match->getKey(),
                'title' => $title,
                'message' => 'You have an active duel against '.($opponent?->username ?? 'another player').'. Open H2H to view handles and submit results.',
                'game' => $gameName,
                'stake' => number_format((float) $match->stake_amount, 2),
            ];
        }

        $dismissedChallenges = session('h2h_dismissed_challenge_prompts', []);

        $challenge = HeadToHeadChallenge::query()
            ->with(['creator', 'game.translations'])
            ->where('status', HeadToHeadChallengeStatus::WAITING)
            ->where('creator_user_id', '!=', $user->getKey())
            ->whereNotIn('id', $dismissedChallenges)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if (! $challenge) {
            return null;
        }

        $gameName = $challenge->game->translations->where('locale', 'en')->first()?->name ?? $challenge->game->slug;

        return [
            'type' => 'challenge',
            'id' => (int) $challenge->getKey(),
            'title' => 'Open duel invitation',
            'message' => ($challenge->creator?->username ?? 'A player').' posted an open duel for this game. Open H2H to review and accept it.',
            'game' => $gameName,
            'stake' => number_format((float) $challenge->stake_amount, 2),
        ];
    }
}
