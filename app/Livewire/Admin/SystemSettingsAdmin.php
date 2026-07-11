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

    public function render()
    {
        return view('livewire.admin.system-settings-admin')->layout('components.layouts.admin', ['admin_title' => 'System Settings']);
    }
}
