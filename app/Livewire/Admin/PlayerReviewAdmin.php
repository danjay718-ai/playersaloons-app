<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Community\Models\PlayerReview;
use Illuminate\Support\Facades\Auth;

class PlayerReviewAdmin extends AdminComponent
{
    public string $status = 'pending';

    public string $notes = '';

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('player_reviews.view'), 403);
    }

    public function moderate(int $id, string $decision): void
    {
        abort_unless($this->actor()->can('player_reviews.manage'), 403);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        PlayerReview::query()->findOrFail($id)->update(['status' => $decision, 'moderated_by' => Auth::id(), 'moderated_at' => now(), 'moderation_notes' => $this->notes ?: null]);
        $this->notes = '';
        session()->flash('success', 'Review moderation updated.');
    }

    public function render()
    {
        return view('livewire.admin.player-review-admin', ['reviews' => PlayerReview::query()->with('user')->when($this->status !== '', fn ($q) => $q->where('status', $this->status))->latest()->paginate(15)])->layout('components.layouts.admin', ['admin_title' => 'Player Reviews']);
    }
}
