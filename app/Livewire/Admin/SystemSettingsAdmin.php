<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Match\Services\AiDisputeSettings;
use App\Modules\Operations\Actions\UpdateAiProviderSettingsAction;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Operations\Services\AiProviderSettings;
use App\Modules\Operations\Services\SystemSettings;
use App\Modules\Tournament\Actions\ResetTournamentTestingDataAction;
use App\Modules\Tournament\Services\TournamentTimezone;
use App\Modules\Wallet\Actions\UpdatePaymentSettingsAction;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Shared\Support\Currency;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class SystemSettingsAdmin extends AdminComponent
{
    use WithFileUploads;

    public string $payoutProvider = 'manual';

    public $payopPayoutCertificate;

    public string $paymentProvider = 'stripe';

    public bool $paymentsEnabled = true;

    public array $paymentCredentials = [];

    public array $resetPaymentCredentials = [];

    public string $currencyCode = 'EUR';

    public bool $referralEnabled = true;

    public string $referrerReward = '5.00';

    public string $referredReward = '2.00';

    public bool $depositFeeEnabled = false;

    public string $depositFeeFixed = '0.00';

    public string $depositFeePercentage = '0.00';

    public bool $withdrawalFeeEnabled = false;

    public string $withdrawalFeePercentage = '10.00';

    public string $platformCommissionPercentage = '10.00';

    public int $defaultWaitingResultTime = 30;

    public string $tournamentTimezone = 'Europe/Amsterdam';

    public bool $showLanguageSwitcherGuest = false;

    public bool $showLanguageSwitcherAdmin = false;

    public int $loginMaxAttempts = 5;

    public int $loginLockoutMinutes = 15;

    public string $disputeNotificationEmail = 'info@playersaloons.com';

    public string $disputeNotificationName = 'PlayerSaloons Disputes';

    public string $aiProvider = AiProviderSettings::PROVIDER_GEMINI;

    /** @var array<string, string> */
    public array $aiApiKeys = [];

    /** @var array<string, string> */
    public array $aiModels = [];

    /** @var array<string, string> */
    public array $aiBaseUrls = [];

    /** @var list<string> */
    public array $resetAiCredentials = [];

    /** @var array{ok: bool, message: string, status: int|null}|null */
    public ?array $aiConnectionTestResult = null;

    public bool $aiDisputesEnabled = false;

    public string $aiDisputesMode = AiDisputeSettings::MODE_SHADOW;

    public string $aiDisputesMinConfidence = '0.95';

    public string $aiDisputesMaxPrizeCap = '50.00';

    public int $aiDisputesEvidenceWindowMinutes = 10;

    public int $aiDisputesMaxRunsPerDispute = 3;

    public bool $showTournamentResetModal = false;

    public string $tournamentResetConfirmation = '';

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('system_settings.view'), 403);
    }

    public function mount(): void
    {
        $values = app(SystemSettings::class)->all();
        $payments = app(PaymentSettings::class);
        $this->paymentProvider = $payments->provider();
        $this->paymentsEnabled = $payments->enabled();
        $this->payoutProvider = $payments->payoutProvider();
        $this->currencyCode = Currency::code();
        $settings = $values;
        $this->referralEnabled = filter_var($settings['referral.enabled'] ?? true, FILTER_VALIDATE_BOOL);
        $this->referrerReward = (string) ($settings['referral.referrer_reward'] ?? '5.00');
        $this->referredReward = (string) ($settings['referral.referred_reward'] ?? '2.00');
        $feeSettings = $values;
        $this->depositFeeEnabled = filter_var($feeSettings['deposit_fee.enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $this->depositFeeFixed = (string) ($feeSettings['deposit_fee.fixed'] ?? '0.00');
        $this->depositFeePercentage = (string) ($feeSettings['deposit_fee.percentage'] ?? '0.00');
        $this->withdrawalFeeEnabled = filter_var($values['withdrawal_fee.enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $this->withdrawalFeePercentage = (string) ($values['withdrawal_fee.percentage'] ?? '10.00');

        $this->platformCommissionPercentage = (string) (
            ($values['platform.commission_percentage'] ?? null)
            ?? ($values['h2h.commission_percentage'] ?? null)
            ?? '10.00'
        );

        $this->defaultWaitingResultTime = (int) (($values['tournament.waiting_result_time_default'] ?? null) ?? 30);
        $this->tournamentTimezone = app(TournamentTimezone::class)->value();

        $langSettings = $values;
        $this->showLanguageSwitcherGuest = filter_var($langSettings['language_switcher.show_guest'] ?? false, FILTER_VALIDATE_BOOL);
        $this->showLanguageSwitcherAdmin = filter_var($langSettings['language_switcher.show_admin'] ?? false, FILTER_VALIDATE_BOOL);

        $authSettings = $values;
        $this->loginMaxAttempts = (int) ($authSettings['auth.login_max_attempts'] ?? 5);
        $this->loginLockoutMinutes = (int) ($authSettings['auth.login_lockout_minutes'] ?? 15);

        $notificationSettings = $values;
        $this->disputeNotificationEmail = (string) ($notificationSettings['notifications.dispute_email'] ?? 'info@playersaloons.com');
        $this->disputeNotificationName = (string) ($notificationSettings['notifications.dispute_name'] ?? 'PlayerSaloons Disputes');

        $aiProviders = app(AiProviderSettings::class);
        $this->aiProvider = $aiProviders->activeProvider();
        foreach (AiProviderSettings::PROVIDERS as $provider) {
            $this->aiModels[$provider] = $aiProviders->model($provider);
            $this->aiBaseUrls[$provider] = $aiProviders->savedBaseUrlOverride($provider);
        }

        $aiDisputes = app(AiDisputeSettings::class);
        $this->aiDisputesEnabled = $aiDisputes->enabled();
        $this->aiDisputesMode = $aiDisputes->mode();
        $this->aiDisputesMinConfidence = number_format($aiDisputes->minConfidence(), 2, '.', '');
        $this->aiDisputesMaxPrizeCap = $aiDisputes->maxPrizeCap();
        $this->aiDisputesEvidenceWindowMinutes = $aiDisputes->evidenceWindowMinutes();
        $this->aiDisputesMaxRunsPerDispute = $aiDisputes->maxRunsPerDispute();
    }

    public function savePaymentSettings(UpdatePaymentSettingsAction $action): void
    {
        $this->authorizeManagement();
        $this->validate(['payopPayoutCertificate' => ['nullable', 'file', 'max:4']]);
        $credentials = [];
        foreach (PaymentSettings::CREDENTIALS as $key) {
            $credentials[$key] = $this->paymentCredentials[str_replace('.', '_', $key)] ?? '';
        }
        if ($this->payopPayoutCertificate instanceof TemporaryUploadedFile) {
            $certificate = $this->payopPayoutCertificate->get();
            if (strlen($certificate) !== 32) {
                $this->addError('payopPayoutCertificate', __('Upload the binary x25519.pub certificate supplied by PayOp.'));

                return;
            }
            $credentials['payop.payout_certificate'] = base64_encode($certificate);
        }
        $action->execute($this->actor(), $this->paymentProvider, $this->paymentsEnabled, $credentials, $this->payoutProvider, $this->resetPaymentCredentials);
        $this->paymentCredentials = [];
        $this->resetPaymentCredentials = [];
        $this->payopPayoutCertificate = null;
        session()->flash('success', __('Payment settings updated.'));
    }

    public function saveCurrencySettings(): void
    {
        $this->authorizeManagement();
        $this->validate(['currencyCode' => ['required', 'in:EUR,GBP,USD']]);
        DB::transaction(function (): void {
            $setting = SystemSetting::query()->firstOrCreate(['key' => 'currency.code'], ['value' => Currency::code()]);
            $setting = SystemSetting::query()->lockForUpdate()->findOrFail($setting->id);
            $hasMoney = SystemSetting::query()->where('key', 'currency.locked')->value('value') === 'true'
                || DB::table('ledger_entries')->exists() || DB::table('payment_checkouts')->exists()
                || DB::table('withdrawals')->exists() || DB::table('wallets')->where('cached_balance', '!=', 0)->exists()
                || DB::table('tournaments')->where('entry_fee', '>', 0)->orWhere('prize_pool', '>', 0)->exists();
            if ($setting->value !== $this->currencyCode && $hasMoney) {
                throw ValidationException::withMessages(['currencyCode' => __('Currency cannot change after financial activity has started.')]);
            }
            app(SystemSettings::class)->save($this->actor(), ['currency.code' => $this->currencyCode]);
        });
        Currency::forget();
        session()->flash('success', __('Currency settings updated.'));
        $this->redirect('/admin/system-settings');
    }

    public function saveTournamentSettings(): void
    {
        $this->authorizeManagement();
        $this->validate(['defaultWaitingResultTime' => ['required', 'integer', 'min:1', 'max:1440']]);
        app(SystemSettings::class)->save($this->actor(), ['tournament.waiting_result_time_default' => (string) $this->defaultWaitingResultTime]);
        session()->flash('success', __('Tournament timing settings updated.'));
    }

    public function saveAppTimezoneSettings(TournamentTimezone $timezone): void
    {
        $this->authorizeManagement();
        $this->validate(['tournamentTimezone' => ['required', 'timezone']]);
        app(SystemSettings::class)->save($this->actor(), ['tournament.timezone' => $this->tournamentTimezone]);
        $timezone->forget();
        session()->flash('success', __('App timezone updated. Refresh other open pages to use the new timezone.'));
        $this->redirect('/admin/system-settings');
    }

    public function savePlatformCommissionSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'platformCommissionPercentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);

        app(SystemSettings::class)->save($this->actor(), ['platform.commission_percentage' => number_format((float) $this->platformCommissionPercentage, 2, '.', '')]);

        session()->flash('success', 'Platform commission settings updated.');
    }

    public function saveDepositFeeSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'depositFeeEnabled' => ['boolean'],
            'depositFeeFixed' => ['required', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
            'depositFeePercentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);

        app(SystemSettings::class)->save($this->actor(), [
            'deposit_fee.enabled' => $this->depositFeeEnabled ? 'true' : 'false',
            'deposit_fee.fixed' => number_format((float) $this->depositFeeFixed, 2, '.', ''),
            'deposit_fee.percentage' => number_format((float) $this->depositFeePercentage, 2, '.', ''),
        ]);

        session()->flash('success', 'Deposit fee settings updated.');
    }

    public function saveWithdrawalFeeSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'withdrawalFeeEnabled' => ['boolean'],
            'withdrawalFeePercentage' => ['required', 'numeric', 'min:0', 'max:99.99', 'decimal:0,2'],
        ]);
        app(SystemSettings::class)->save($this->actor(), [
            'withdrawal_fee.enabled' => $this->withdrawalFeeEnabled ? 'true' : 'false',
            'withdrawal_fee.percentage' => number_format((float) $this->withdrawalFeePercentage, 2, '.', ''),
        ]);
        session()->flash('success', __('Withdrawal fee settings updated.'));
    }

    public function saveReferralSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'referralEnabled' => ['boolean'],
            'referrerReward' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'referredReward' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
        ]);

        app(SystemSettings::class)->save($this->actor(), [
            'referral.enabled' => $this->referralEnabled ? 'true' : 'false',
            'referral.referrer_reward' => number_format((float) $this->referrerReward, 2, '.', ''),
            'referral.referred_reward' => number_format((float) $this->referredReward, 2, '.', ''),
        ]);

        session()->flash('success', 'Referral settings updated.');
    }

    public function saveLanguageSwitcherSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'showLanguageSwitcherGuest' => ['boolean'],
            'showLanguageSwitcherAdmin' => ['boolean'],
        ]);

        app(SystemSettings::class)->save($this->actor(), [
            'language_switcher.show_guest' => $this->showLanguageSwitcherGuest ? 'true' : 'false',
            'language_switcher.show_admin' => $this->showLanguageSwitcherAdmin ? 'true' : 'false',
        ]);

        session()->flash('success', 'Language switcher settings updated.');
    }

    public function saveAuthenticationSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'loginMaxAttempts' => ['required', 'integer', 'min:3', 'max:20'],
            'loginLockoutMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        app(SystemSettings::class)->save($this->actor(), [
            'auth.login_max_attempts' => (string) $this->loginMaxAttempts,
            'auth.login_lockout_minutes' => (string) $this->loginLockoutMinutes,
        ]);

        session()->flash('success', 'Authentication security settings updated.');
    }

    public function saveDisputeNotificationSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'disputeNotificationEmail' => ['required', 'email:rfc', 'max:254'],
            'disputeNotificationName' => ['required', 'string', 'max:100'],
        ]);

        app(SystemSettings::class)->save($this->actor(), [
            'notifications.dispute_email' => strtolower(trim($this->disputeNotificationEmail)),
            'notifications.dispute_name' => trim($this->disputeNotificationName),
        ]);

        session()->flash('success', 'Dispute notification settings updated.');
    }

    public function saveAiProviderSettings(UpdateAiProviderSettingsAction $action): void
    {
        $this->authorizeManagement();

        $action->execute(
            $this->actor(),
            $this->aiProvider,
            $this->aiApiKeys,
            $this->aiModels,
            $this->aiBaseUrls,
            $this->resetAiCredentials,
        );

        $this->aiApiKeys = [];
        $this->resetAiCredentials = [];
        $this->aiConnectionTestResult = null;
        session()->flash('success', __('AI provider & API settings updated.'));
    }

    public function testAiProviderConnection(AiProviderSettings $providers): void
    {
        $this->authorizeManagement();

        $this->aiConnectionTestResult = $providers->testConnection($this->aiProvider);
    }

    public function saveAiDisputeSettings(): void
    {
        $this->authorizeManagement();
        $this->validate([
            'aiDisputesEnabled' => ['boolean'],
            'aiDisputesMode' => ['required', 'in:shadow,auto_resolve'],
            'aiDisputesMinConfidence' => ['required', 'numeric', 'min:0.50', 'max:1.00'],
            'aiDisputesMaxPrizeCap' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'aiDisputesEvidenceWindowMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'aiDisputesMaxRunsPerDispute' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        app(SystemSettings::class)->save($this->actor(), [
            'ai.disputes.enabled' => $this->aiDisputesEnabled ? 'true' : 'false',
            'ai.disputes.mode' => $this->aiDisputesMode,
            'ai.disputes.min_confidence' => number_format((float) $this->aiDisputesMinConfidence, 2, '.', ''),
            'ai.disputes.max_prize_cap' => number_format((float) $this->aiDisputesMaxPrizeCap, 2, '.', ''),
            'ai.disputes.evidence_window_minutes' => (string) $this->aiDisputesEvidenceWindowMinutes,
            'ai.disputes.max_runs_per_dispute' => (string) $this->aiDisputesMaxRunsPerDispute,
        ]);

        session()->flash('success', __('AI dispute assistant settings updated.'));
    }

    public function openTournamentTestingReset(): void
    {
        $this->authorizeTournamentTestingReset();
        $this->tournamentResetConfirmation = '';
        $this->showTournamentResetModal = true;
    }

    public function resetTournamentTestingData(ResetTournamentTestingDataAction $reset): void
    {
        $this->authorizeTournamentTestingReset();
        $this->validate(['tournamentResetConfirmation' => ['required', 'in:DELETE TOURNAMENT TEST DATA']]);

        $summary = $reset->execute();
        $this->showTournamentResetModal = false;
        $this->tournamentResetConfirmation = '';
        session()->flash('success', sprintf(
            'Tournament test reset completed: %d occurrence(s), %d template(s), %d linked ledger entry/entries, and %d XP award(s) removed.',
            $summary['tournaments'], $summary['templates'], $summary['ledger_entries'], $summary['experience_awards'],
        ));
    }

    public function render()
    {
        return view('livewire.admin.system-settings-admin', [
            'timezones' => DateTimeZone::listIdentifiers(),
            'paymentCredentialFields' => [
                'payop_public_key' => ['key' => 'payop.public_key', 'label' => 'PayOp public key'],
                'payop_secret' => ['key' => 'payop.secret', 'label' => 'PayOp secret key'],
                'stripe_secret' => ['key' => 'stripe.secret', 'label' => 'Stripe secret key'],
                'stripe_webhook_secret' => ['key' => 'stripe.webhook_secret', 'label' => 'Stripe webhook secret'],
                'payop_payout_token' => ['key' => 'payop.payout_token', 'label' => 'PayOp payout JWT token'],
            ],
            'paymentCredentialSources' => collect(PaymentSettings::CREDENTIALS)->mapWithKeys(fn (string $key): array => [$key => app(PaymentSettings::class)->source($key)])->all(),
            'configuredPaymentCredentials' => collect(PaymentSettings::CREDENTIALS)->mapWithKeys(
                fn (string $key): array => [$key => app(PaymentSettings::class)->credential($key) !== '']
            )->all(),
            'aiProviderCatalog' => app(AiProviderSettings::class)->catalog(),
            'aiProviderConfigured' => collect(AiProviderSettings::PROVIDERS)->mapWithKeys(
                fn (string $provider): array => [$provider => app(AiProviderSettings::class)->isConfigured($provider)]
            )->all(),
            'aiProviderSources' => collect(AiProviderSettings::PROVIDERS)->mapWithKeys(
                fn (string $provider): array => [$provider => app(AiProviderSettings::class)->source($provider)]
            )->all(),
        ])->layout('components.layouts.admin', ['admin_title' => 'System Settings']);
    }

    private function authorizeManagement(): void
    {
        abort_unless($this->actor()->can('system_settings.manage'), 403);
    }

    private function authorizeTournamentTestingReset(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && $this->actor()->hasRole('SUPER_ADMIN'), 403);
    }
}
