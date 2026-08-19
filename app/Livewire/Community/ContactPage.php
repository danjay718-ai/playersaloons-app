<?php

declare(strict_types=1);

namespace App\Livewire\Community;

use App\Modules\Community\Models\ContactInquiry;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class ContactPage extends Component
{
    public string $name = '';

    public string $email = '';

    public string $category = 'general';

    public string $subject = '';

    public string $message = '';

    /**
     * @var array<string, string>
     */
    public array $categories = [
        'general' => 'General question',
        'account' => 'Account support',
        'tournament' => 'Tournament support',
        'wallet' => 'Wallet or payment',
        'kyc' => 'KYC verification',
        'bug' => 'Bug report',
    ];

    public function mount(): void
    {
        $user = Auth::user();

        if ($user) {
            $this->name = $user->profile?->display_name ?: $user->username;
            $this->email = $user->email;
        }

        $reference = request()->query('reference');

        if (is_string($reference) && preg_match('/\AERR-[A-Z0-9-]{8,64}\z/', $reference) === 1) {
            $this->category = 'bug';
            $this->subject = 'System error report '.$reference;
            $this->message = "I encountered a system error.\n\nSupport reference: {$reference}\n\nWhat I was doing when it happened: ";
        }
    }

    public function submit(NotificationService $notificationService): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys($this->categories))],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $user = Auth::user();

        $inquiry = ContactInquiry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user?->getKey(),
            'name' => $this->name,
            'email' => $this->email,
            'category' => $this->category,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => 'new',
        ]);

        User::role(['SUPER_ADMIN', 'ADMIN', 'SUPPORT_AGENT'])
            ->get()
            ->each(function (User $staff) use ($notificationService, $inquiry): void {
                $notificationService->send(
                    $staff,
                    'contact_inquiry',
                    'New contact inquiry',
                    "{$inquiry->name} submitted: {$inquiry->subject}"
                );
            });

        $this->reset(['subject', 'message']);
        $this->category = 'general';

        session()->flash('success', 'Your message was sent. Our team will review it soon.');
    }

    public function render()
    {
        $view = view('livewire.community.contact-page');
        $user = Auth::user();

        if ($user && $user->hasVerifiedEmail()) {
            return $view->layout('components.layouts.dashboard', [
                'title' => 'Contact Support | PlayerSaloons',
            ]);
        }

        return $view->layout('components.layouts.app', [
            'title' => 'Contact Support | PlayerSaloons',
        ]);
    }
}
