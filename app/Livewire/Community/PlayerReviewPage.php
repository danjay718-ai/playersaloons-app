<?php

declare(strict_types=1);

namespace App\Livewire\Community;

use App\Modules\Community\Models\PlayerReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class PlayerReviewPage extends Component
{
    public int $rating = 5;

    public string $review = '';

    public function mount(): void
    {
        $existing = PlayerReview::query()->where('user_id', Auth::id())->first();
        if ($existing) {
            $this->rating = $existing->rating;
            $this->review = $existing->review;
        }
    }

    public function submit(): void
    {
        $data = $this->validate(['rating' => ['required', 'integer', 'between:1,5'], 'review' => ['required', 'string', 'min:10', 'max:1000']]);
        PlayerReview::query()->updateOrCreate(['user_id' => Auth::id()], ['uuid' => PlayerReview::query()->where('user_id', Auth::id())->value('uuid') ?? Str::uuid(), 'rating' => $data['rating'], 'review' => $data['review'], 'status' => 'pending', 'moderated_by' => null, 'moderated_at' => null, 'moderation_notes' => null]);
        session()->flash('success', 'Your review was submitted for moderation.');
    }

    public function render()
    {
        return view('livewire.community.player-review-page', ['existing' => PlayerReview::query()->where('user_id', Auth::id())->first()])->layout('components.layouts.dashboard', ['title' => 'Review PlayerSaloons', 'dashboard_title' => 'PLAYER REVIEW']);
    }
}
