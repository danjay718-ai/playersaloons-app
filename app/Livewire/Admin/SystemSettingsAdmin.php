<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Operations\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;

class SystemSettingsAdmin extends AdminComponent
{
    public bool $referralEnabled = true;

    public string $referrerReward = '5.00';

    public string $referredReward = '2.00';

    public bool $depositFeeEnabled = false;

    public string $depositFeeFixed = '0.00';

    public string $depositFeePercentage = '0.00';

    public string $h2hCommissionPercentage = '10.00';

    public int $defaultWaitingResultTime = 30;

    public bool $showLanguageSwitcherGuest = false;

    public bool $showLanguageSwitcherAdmin = false;

    public int $loginMaxAttempts = 5;

    public int $loginLockoutMinutes = 15;

    public function boot(): void
    {
        parent::boot();
        if (! Auth::user()?->hasAnyRole(['SUPER_ADMIN', 'ADMIN'])) {
            abort(403);
        }
    }

    public function mount(): void
    {
        $settings = SystemSetting::query()->whereIn('key', ['referral.enabled', 'referral.referrer_reward', 'referral.referred_reward'])->pluck('value', 'key');
        $this->referralEnabled = filter_var($settings['referral.enabled'] ?? true, FILTER_VALIDATE_BOOL);
        $this->referrerReward = (string) ($settings['referral.referrer_reward'] ?? '5.00');
        $this->referredReward = (string) ($settings['referral.referred_reward'] ?? '2.00');
        $feeSettings = SystemSetting::query()->whereIn('key', ['deposit_fee.enabled', 'deposit_fee.fixed', 'deposit_fee.percentage'])->pluck('value', 'key');
        $this->depositFeeEnabled = filter_var($feeSettings['deposit_fee.enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $this->depositFeeFixed = (string) ($feeSettings['deposit_fee.fixed'] ?? '0.00');
        $this->depositFeePercentage = (string) ($feeSettings['deposit_fee.percentage'] ?? '0.00');

        $this->h2hCommissionPercentage = (string) (SystemSetting::query()->where('key', 'h2h.commission_percentage')->value('value') ?? '10.00');

        $this->defaultWaitingResultTime = (int) (SystemSetting::query()->where('key', 'tournament.waiting_result_time_default')->value('value') ?? 30);

        $langSettings = SystemSetting::query()->whereIn('key', ['language_switcher.show_guest', 'language_switcher.show_admin'])->pluck('value', 'key');
        $this->showLanguageSwitcherGuest = filter_var($langSettings['language_switcher.show_guest'] ?? false, FILTER_VALIDATE_BOOL);
        $this->showLanguageSwitcherAdmin = filter_var($langSettings['language_switcher.show_admin'] ?? false, FILTER_VALIDATE_BOOL);

        $authSettings = SystemSetting::query()->whereIn('key', ['auth.login_max_attempts', 'auth.login_lockout_minutes'])->pluck('value', 'key');
        $this->loginMaxAttempts = (int) ($authSettings['auth.login_max_attempts'] ?? 5);
        $this->loginLockoutMinutes = (int) ($authSettings['auth.login_lockout_minutes'] ?? 15);
    }

    public function saveTournamentSettings(): void
    {
        $this->validate(['defaultWaitingResultTime' => ['required', 'integer', 'min:1', 'max:1440']]);
        SystemSetting::query()->updateOrCreate(
            ['key' => 'tournament.waiting_result_time_default'],
            ['value' => (string) $this->defaultWaitingResultTime, 'updated_by' => Auth::id()]
        );
        session()->flash('success', 'Tournament timing settings updated.');
    }

    public function saveH2hSettings(): void
    {
        $this->validate([
            'h2hCommissionPercentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);

        SystemSetting::query()->updateOrCreate(
            ['key' => 'h2h.commission_percentage'],
            ['value' => number_format((float) $this->h2hCommissionPercentage, 2, '.', ''), 'updated_by' => Auth::id()]
        );

        session()->flash('success', 'H2H settings updated.');
    }

    public function saveDepositFeeSettings(): void
    {
        $this->validate([
            'depositFeeEnabled' => ['boolean'],
            'depositFeeFixed' => ['required', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
            'depositFeePercentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);

        foreach ([
            'deposit_fee.enabled' => $this->depositFeeEnabled ? 'true' : 'false',
            'deposit_fee.fixed' => number_format((float) $this->depositFeeFixed, 2, '.', ''),
            'deposit_fee.percentage' => number_format((float) $this->depositFeePercentage, 2, '.', ''),
        ] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
        }

        session()->flash('success', 'Deposit fee settings updated.');
    }

    public function saveReferralSettings(): void
    {
        $this->validate([
            'referralEnabled' => ['boolean'],
            'referrerReward' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'referredReward' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
        ]);

        foreach ([
            'referral.enabled' => $this->referralEnabled ? 'true' : 'false',
            'referral.referrer_reward' => number_format((float) $this->referrerReward, 2, '.', ''),
            'referral.referred_reward' => number_format((float) $this->referredReward, 2, '.', ''),
        ] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
        }

        session()->flash('success', 'Referral settings updated.');
    }

    public function saveLanguageSwitcherSettings(): void
    {
        $this->validate([
            'showLanguageSwitcherGuest' => ['boolean'],
            'showLanguageSwitcherAdmin' => ['boolean'],
        ]);

        foreach ([
            'language_switcher.show_guest' => $this->showLanguageSwitcherGuest ? 'true' : 'false',
            'language_switcher.show_admin' => $this->showLanguageSwitcherAdmin ? 'true' : 'false',
        ] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
        }

        session()->flash('success', 'Language switcher settings updated.');
    }

    public function saveAuthenticationSettings(): void
    {
        $this->validate([
            'loginMaxAttempts' => ['required', 'integer', 'min:3', 'max:20'],
            'loginLockoutMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        foreach ([
            'auth.login_max_attempts' => (string) $this->loginMaxAttempts,
            'auth.login_lockout_minutes' => (string) $this->loginLockoutMinutes,
        ] as $key => $value) {
            SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => Auth::id()]);
        }

        session()->flash('success', 'Authentication security settings updated.');
    }

    public function render()
    {
        return view('livewire.admin.system-settings-admin')->layout('components.layouts.admin', ['admin_title' => 'System Settings']);
    }
}
