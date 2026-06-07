<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Identity\Actions\SubmitKycAction;
use App\Modules\Identity\Actions\UpdateProfileAction;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProfileDashboard extends Component
{
    use WithFileUploads;

    // Profile Details
    public string $displayName = '';

    public string $bio = '';

    public string $countryCode = '';

    public string $timezone = '';

    // KYC Submission
    public string $documentType = 'id_card';

    /** @var mixed */
    public $kycFile = null;

    // Notification Preferences
    public bool $emailNotifications = true;

    public bool $inAppNotifications = true;

    public bool $realtimeNotifications = true;

    public function mount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->redirect('/login');

            return;
        }

        $profile = $user->profile;
        if ($profile) {
            $this->displayName = $profile->display_name ?? '';
            $this->bio = $profile->bio ?? '';
            $this->countryCode = $profile->country_code ?? '';
            $this->timezone = $profile->timezone ?? '';
        }

        $pref = NotificationPreference::query()->where('user_id', $user->id)->first();
        if ($pref) {
            $this->emailNotifications = (bool) $pref->email_enabled;
            $this->inAppNotifications = (bool) $pref->in_app_enabled;
            $this->realtimeNotifications = (bool) $pref->realtime_enabled;
        }
    }

    public function updateProfile(UpdateProfileAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'displayName' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'countryCode' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        try {
            $action->execute($user, [
                'display_name' => $this->displayName,
                'bio' => $this->bio,
                'country_code' => $this->countryCode,
                'timezone' => $this->timezone,
            ]);

            session()->flash('message', 'Profile updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitKyc(SubmitKycAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'documentType' => ['required', 'string', 'in:passport,id_card,drivers_license'],
            'kycFile' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg'],
        ]);

        try {
            $action->execute($user, $this->documentType, [$this->kycFile]);
            session()->flash('message', 'KYC document submitted successfully! Our compliance team will review it.');
            $this->reset('kycFile');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function updatePreferences(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            NotificationPreference::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'email_enabled' => $this->emailNotifications,
                    'in_app_enabled' => $this->inAppNotifications,
                    'realtime_enabled' => $this->realtimeNotifications,
                ]
            );

            session()->flash('message', 'Notification preferences updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * @param  View|Factory  $view
     * @return mixed
     */
    private function resolveView($view)
    {
        return $view;
    }

    /**
     * @return mixed
     */
    public function render()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->redirect('/login');

            return view('livewire.profile.profile-dashboard', [
                'user' => null,
                'latestKyc' => null,
            ]);
        }

        $latestKyc = KycSubmission::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $view = view('livewire.profile.profile-dashboard', [
            'user' => $user,
            'latestKyc' => $latestKyc,
        ]);

        return $this->resolveView($view)->layout('components.layouts.app', ['title' => 'My Profile | PlayerSaloons']);
    }
}
