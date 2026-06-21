<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Identity\Actions\SubmitKycAction;
use App\Modules\Identity\Actions\UpdateProfileAction;
use App\Modules\Identity\Actions\UploadAvatarAction;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class ProfileDashboard extends Component
{
    use WithFileUploads;

    // Profile Details
    public string $displayName = '';

    public string $bio = '';

    public string $countryCode = '';

    public string $timezone = '';

    public string $username = '';

    public string $email = '';

    /** @var mixed */
    public $avatarFile = null;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

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

        if ($user->hasAnyRole([
            'SUPER_ADMIN',
            'ADMIN',
            'MODERATOR',
            'TOURNAMENT_ORGANIZER',
            'SUPPORT_AGENT',
            'FINANCE_OPERATOR',
            'KYC_REVIEWER',
        ])) {
            $this->redirect('/admin/profile');

            return;
        }

        $profile = $user->profile;
        if ($profile) {
            $this->displayName = $profile->display_name ?? '';
            $this->bio = $profile->bio ?? '';
            $this->countryCode = $profile->country_code ?? '';
            $this->timezone = $profile->timezone ?? '';
        }

        $this->username = (string) $user->username;
        $this->email = (string) $user->email;

        $pref = NotificationPreference::query()->where('user_id', $user->id)->first();
        if ($pref) {
            $this->emailNotifications = (bool) $pref->email_enabled;
            $this->inAppNotifications = (bool) $pref->in_app_enabled;
            $this->realtimeNotifications = (bool) $pref->realtime_enabled;
        }
    }

    public function updateAccount(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = $this->email !== $user->email;

        $user->fill([
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ]);
        $user->save();

        session()->flash('message', $emailChanged
            ? 'Account updated. Please verify your new email address.'
            : 'Account updated successfully!');
    }

    public function updateAvatar(UploadAvatarAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'avatarFile' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $action->execute($user, $this->avatarFile);
        $this->reset('avatarFile');

        session()->flash('message', 'Profile picture updated successfully!');
    }

    public function updatePassword(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8', 'same:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string'],
        ]);

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'The current password is incorrect.');

            return;
        }

        $user->forceFill([
            'password' => $this->newPassword,
        ])->save();

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        session()->flash('message', 'Password changed successfully!');
    }

    public function verifyEmail(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || $user->email_verified_at !== null) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        EmailVerified::dispatch((int) $user->getKey());

        session()->flash('message', 'Email verified successfully!');
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
            $this->forgetProfileCaches((int) $user->id);
            $this->dispatch('profile-kyc-submitted');
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

            $this->forgetProfileCaches((int) $user->id);
            session()->flash('message', 'Notification preferences updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function updateNotificationPreference(string $preference, bool $enabled): void
    {
        if (! in_array($preference, ['emailNotifications', 'inAppNotifications', 'realtimeNotifications'], true)) {
            return;
        }

        $this->{$preference} = $enabled;
        $this->updatePreferences();
        $this->skipRender();
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
                'timezoneOptions' => [],
            ]);
        }

        $latestKyc = $this->latestKycFor((int) $user->id);

        return view('livewire.profile.profile-dashboard', [
            'user' => $user,
            'latestKyc' => $latestKyc,
            'timezoneOptions' => $this->timezoneOptions(),
        ])->layout('components.layouts.dashboard', [
            'title' => 'My Profile | PlayerSaloons',
            'dashboard_title' => 'USER PROFILE',
        ]);
    }

    /**
     * @return list<string>
     */
    private function timezoneOptions(): array
    {
        /** @var list<string> $timezones */
        $timezones = $this->rememberInRedis('profile:common-timezones', 86400, fn (): array => [
            'UTC',
            'Asia/Manila',
            'Asia/Singapore',
            'Asia/Tokyo',
            'Asia/Seoul',
            'Asia/Hong_Kong',
            'Australia/Sydney',
            'Europe/London',
            'Europe/Paris',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
        ]);

        if ($this->timezone !== '' && ! in_array($this->timezone, $timezones, true)) {
            $timezones[] = $this->timezone;
        }

        sort($timezones);

        return array_values($timezones);
    }

    private function latestKycFor(int $userId): ?KycSubmission
    {
        /** @var int|null $latestKycId */
        $latestKycId = $this->rememberInRedis(
            "profile:{$userId}:latest-kyc-id",
            30,
            fn (): ?int => KycSubmission::query()
                ->where('user_id', $userId)
                ->latest('id')
                ->value('id')
        );

        if (! $latestKycId) {
            return null;
        }

        return KycSubmission::query()->find($latestKycId);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function rememberInRedis(string $key, int $seconds, callable $callback): mixed
    {
        try {
            return Cache::store('redis')->remember($key, now()->addSeconds($seconds), $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    private function forgetProfileCaches(int $userId): void
    {
        try {
            Cache::store('redis')->forget("profile:{$userId}:latest-kyc-id");
        } catch (Throwable) {
            // Redis cache is an optimization only; database writes remain authoritative.
        }
    }
}
