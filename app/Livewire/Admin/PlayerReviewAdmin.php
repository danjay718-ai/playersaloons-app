<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\PlayerReview;
use Illuminate\Support\Facades\Auth;

class PlayerReviewAdmin extends AdminComponent
{
    public string $status = 'pending';

    /** @var array<int, string> */
    public array $notes = [];

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('player_reviews.view'), 403);
    }

    public function moderate(int $id, string $decision): void
    {
        abort_unless($this->actor()->can('player_reviews.manage'), 403);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $this->validate(['notes.'.$id => ['nullable', 'string', 'max:1000']], [], ['notes.'.$id => __('internal note')]);
        $review = PlayerReview::query()->findOrFail($id);
        $review->update([
            'status' => $decision,
            'moderated_by' => Auth::id(),
            'moderated_at' => now(),
            'moderation_notes' => trim($this->notes[$id] ?? $review->moderation_notes ?? '') ?: null,
        ]);
        unset($this->notes[$id]);
        session()->flash('success', 'Review moderation updated.');
    }

    public function render()
    {
        $reviews = PlayerReview::query()->with('user')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest()->paginate(15);
        foreach ($reviews as $review) {
            if (! array_key_exists($review->id, $this->notes)) {
                $this->notes[$review->id] = $review->moderation_notes ?? '';
            }
        }

        return view('livewire.admin.player-review-admin', compact('reviews'))
            ->layout('components.layouts.admin', ['admin_title' => 'Player Reviews']);
    }
}
