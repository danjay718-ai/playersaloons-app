<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Mail\NewsletterCampaignMail;
use App\Modules\Community\Models\NewsletterCampaign;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use Throwable;

class NewsletterAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $subject = '';

    public string $content = '';

    public function boot(): void
    {
        parent::boot();


        if (! $this->actor()->hasAnyRole(['SUPER_ADMIN', 'ADMIN'])) {
            abort(403, 'Only administrators can manage newsletter campaigns.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sendCampaign(): void
    {
        $validated = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:10000'],
        ]);

        $recipientCount = $this->subscriberQuery()->count();
        if ($recipientCount === 0) {
            session()->flash('error', 'There are no eligible newsletter subscribers.');

            return;
        }

        $campaign = NewsletterCampaign::query()->create([
            'uuid' => Str::uuid()->toString(),
            'subject' => $validated['subject'],
            'content' => $validated['content'],
            'status' => 'sending',
            'recipient_count' => $recipientCount,
            'created_by' => Auth::id(),
        ]);

        $sent = 0;
        $failed = 0;

        $this->subscriberQuery()->eachById(function (User $user) use ($campaign, &$sent, &$failed): void {
            try {
                Mail::to($user->email)->send(new NewsletterCampaignMail($campaign, $user));
                $sent++;
            } catch (Throwable $exception) {
                $failed++;
                Log::warning('Newsletter delivery failed.', [
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });

        $campaign->update([
            'status' => $failed > 0 ? 'completed_with_errors' : 'sent',
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
        ]);

        $this->reset(['subject', 'content']);
        session()->flash('success', "Campaign sent to {$sent} subscriber(s).".($failed > 0 ? " {$failed} delivery attempt(s) failed." : ''));
    }

    private function subscriberQuery()
    {
        return User::query()
            ->where('newsletter_subscribed', true)
            ->where('status', UserStatus::ACTIVE->value)
            ->whereNotNull('email_verified_at');
    }

    public function render()
    {
        $audience = $this->subscriberQuery()->latest('newsletter_subscribed_at');

        if ($this->search !== '') {
            $audience->where(function ($query): void {
                $query->where('email', 'like', '%'.$this->search.'%')
                    ->orWhere('username', 'like', '%'.$this->search.'%');
            });
        }

        return view('livewire.admin.newsletter-admin', [
            'subscribers' => $audience->paginate(15),
            'subscriberCount' => $this->subscriberQuery()->count(),
            'campaigns' => NewsletterCampaign::query()->with('creator')->latest()->limit(10)->get(),
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Newsletter Management',
        ]);
    }
}
