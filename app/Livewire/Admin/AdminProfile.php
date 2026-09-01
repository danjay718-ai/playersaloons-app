<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Actions\UpdateProfileAction;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdminProfile extends AdminComponent
{
    public string $displayName = '';

    public string $bio = '';

    public string $countryCode = '';

    public string $timezone = '';

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
            'countryCode' => ['nullable', 'string', 'size:2', 'in:'.implode(',', array_keys(config('countries', [])))],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        try {
            $action->execute($user, [
                'display_name' => $this->displayName,
                'bio' => $this->bio,
                'country_code' => $this->countryCode,
                'timezone' => $this->timezone,
            ]);

            session()->flash('message', 'Admin profile updated successfully!');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to update the admin profile.'));
        }
    }

    public function resendEmailVerification(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null || $user->hasVerifiedEmail()) {
            return;
        }
        if (! Cache::add("admin-email-verification:{$user->id}", true, now()->addMinute())) {
            session()->flash('error', 'Please wait before requesting another verification email.');

            return;
        }

        $user->sendEmailVerificationNotification();
        session()->flash('message', 'A new email verification link has been sent.');
    }

    public function render()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->redirect('/login');

            return view('livewire.admin.admin-profile', ['user' => null]);
        }

        return view('livewire.admin.admin-profile', [
            'user' => $user,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Profile Configuration',
        ]);
    }
}
