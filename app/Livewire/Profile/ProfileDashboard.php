<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Compliance\Services\CountryEligibilityService;
use App\Modules\Identity\Actions\DisableTwoFactorAction;
use App\Modules\Identity\Actions\EnableTwoFactorAction;
use App\Modules\Identity\Actions\SubmitKycAction;
use App\Modules\Identity\Actions\UpdateProfileAction;
use App\Modules\Identity\Actions\UploadAvatarAction;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class ProfileDashboard extends Component
{
    use HandlesUserFacingErrors, WithFileUploads;

    // Profile Details
    public string $displayName = '';

    public string $fullName = '';

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

    public string $twoFactorSetupSecret = '';

    public string $twoFactorCode = '';

    public string $twoFactorPassword = '';

    /** @var list<string> */
    public array $twoFactorRecoveryCodes = [];

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
            $this->fullName = $profile->full_name ?? '';
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

        if (! $user->hasVerifiedEmail()) {
            $this->emailNotifications = false;
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

        if ($emailChanged) {
            $this->emailNotifications = false;
            NotificationPreference::query()
                ->where('user_id', $user->id)
                ->update(['email_enabled' => false]);
            $user->sendEmailVerificationNotification();
        }

        session()->flash('message', $emailChanged
            ? 'Account updated. Please verify your new email address.'
            : 'Account updated successfully!');
        $this->dispatch('account-updated');
    }

    public function updateAvatar(UploadAvatarAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'avatarFile' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:width=400,height=400'],
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
            'newPassword' => ['required', 'string', 'same:newPasswordConfirmation', Password::defaults()],
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

    public function resendEmailVerification(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user || $user->email_verified_at !== null) {
            return;
        }

        $user->sendEmailVerificationNotification();
        session()->flash('message', 'Verification email sent. Check your inbox to complete verification.');
    }

    public function beginTwoFactorSetup(TotpService $totp): void
    {
        $user = Auth::user();
        if (! $user || $user->two_factor_confirmed_at) {
            return;
        }

        $this->twoFactorSetupSecret = $totp->generateSecret();
        $this->twoFactorCode = '';
        $this->twoFactorRecoveryCodes = [];
    }

    public function confirmTwoFactor(EnableTwoFactorAction $action): void
    {
        $user = Auth::user();
        if (! $user || $this->twoFactorSetupSecret === '') {
            return;
        }

        $this->validate(['twoFactorCode' => ['required', 'digits:6']]);
        $this->twoFactorRecoveryCodes = $action->execute($user, $this->twoFactorSetupSecret, $this->twoFactorCode);
        $this->reset('twoFactorSetupSecret', 'twoFactorCode');
        session()->flash('message', 'Two-factor authentication enabled. Store your recovery codes securely.');
    }

    public function disableTwoFactor(DisableTwoFactorAction $action): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate(['twoFactorPassword' => ['required', 'string']]);
        $action->execute($user, $this->twoFactorPassword);
        $this->reset('twoFactorPassword', 'twoFactorRecoveryCodes');
        session()->flash('message', 'Two-factor authentication disabled.');
    }

    public function updateProfile(UpdateProfileAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->validate([
            'fullName' => ['nullable', 'string', 'min:2', 'max:150', 'regex:/^[\pL\pM][\pL\pM .\'\-]*$/u'],
            'displayName' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'countryCode' => ['nullable', 'string', 'size:2', Rule::in(array_keys(app(CountryEligibilityService::class)->selectableCountries()))],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        try {
            $action->execute($user, [
                'full_name' => trim($this->fullName) ?: null,
                'display_name' => $this->displayName,
                'bio' => $this->bio,
                'country_code' => $this->countryCode,
                'timezone' => $this->timezone,
            ]);

            session()->flash('message', 'Profile updated successfully!');
            $this->dispatch('profile-updated');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to update the profile.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to submit the KYC document.'));
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
                    'email_enabled' => $user->hasVerifiedEmail() && $this->emailNotifications,
                    'in_app_enabled' => $this->inAppNotifications,
                    'realtime_enabled' => $this->realtimeNotifications,
                ]
            );

            $this->forgetProfileCaches((int) $user->id);
            session()->flash('message', 'Notification preferences updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to update notification preferences.'));
        }
    }

    public function updateNotificationPreference(string $preference, bool $enabled): void
    {
        if (! in_array($preference, ['emailNotifications', 'inAppNotifications', 'realtimeNotifications'], true)) {
            return;
        }

        $user = Auth::user();
        if ($preference === 'emailNotifications' && ! $user?->hasVerifiedEmail()) {
            $this->emailNotifications = false;
            session()->flash('error', 'Verify your email before enabling email notifications.');

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
                'countries' => [],
            ]);
        }

        $latestKyc = $this->latestKycFor((int) $user->id);

        return view('livewire.profile.profile-dashboard', [
            'user' => $user,
            'latestKyc' => $latestKyc,
            'timezoneOptions' => $this->timezoneOptions(),
            'countries' => app(CountryEligibilityService::class)->selectableCountries(),
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
