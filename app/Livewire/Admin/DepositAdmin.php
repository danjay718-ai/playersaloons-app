<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Wallet\Models\Deposit;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Shared\Support\DecimalMoney;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Deposit History')]
class DepositAdmin extends AdminComponent
{
    use WithPagination;

    #[Url]
    public string $viewMode = 'deposits';

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $providerFilter = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public ?int $selectedDepositId = null;

    public ?string $selectedCheckoutId = null;

    public bool $showDetailModal = false;

    public function boot(): void
    {
        parent::boot();

        abort_unless($this->actor()->can('deposits.view'), 403);
    }

    public function mount(): void
    {
        if (! in_array($this->viewMode, ['deposits', 'checkouts'], true)) {
            $this->viewMode = 'deposits';
        }

        if (
            $this->search === ''
            && $this->statusFilter === ''
            && $this->providerFilter === ''
            && $this->dateFrom === ''
            && $this->dateTo === ''
        ) {
            $this->dateFrom = now()->subDays(30)->toDateString();
        }
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['deposits', 'checkouts'], true)) {
            return;
        }

        $this->viewMode = $mode;
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProviderFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->providerFilter = '';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function applyLast30Days(): void
    {
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = '';
        $this->resetPage();
    }

    public function selectDeposit(int $depositId): void
    {
        $this->selectedCheckoutId = null;
        $this->selectedDepositId = $depositId;
        $this->showDetailModal = true;
    }

    public function selectCheckout(string $checkoutId): void
    {
        $this->selectedDepositId = null;
        $this->selectedCheckoutId = $checkoutId;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedDepositId = null;
        $this->selectedCheckoutId = null;
    }

    /**
     * @return Builder<Deposit>
     */
    private function filteredDepositsQuery(bool $applyStatus = true): Builder
    {
        $query = Deposit::query();

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('provider_reference', 'like', "%{$search}%")
                    ->orWhereHas('wallet.user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($applyStatus && $this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->providerFilter !== '') {
            $query->where('provider', $this->providerFilter);
        }

        $from = $this->parseDate($this->dateFrom);
        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        $to = $this->parseDate($this->dateTo);
        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query;
    }

    /**
     * @return Builder<PaymentCheckout>
     */
    private function filteredCheckoutsQuery(bool $applyStatus = true): Builder
    {
        $query = PaymentCheckout::query()->whereNull('completed_at');

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('provider_reference', 'like', "%{$search}%")
                    ->orWhereHas('wallet.user', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($applyStatus && $this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->providerFilter !== '') {
            $query->where('provider', $this->providerFilter);
        }

        $from = $this->parseDate($this->dateFrom);
        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        $to = $this->parseDate($this->dateTo);
        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return $query;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sumToMinor(mixed $value): int
    {
        return DecimalMoney::toMinor(number_format((float) ($value ?? 0), 2, '.', ''));
    }

    public function render(): View
    {
        $depositsBase = $this->filteredDepositsQuery($this->viewMode === 'deposits');
        $checkoutsBase = $this->filteredCheckoutsQuery($this->viewMode === 'checkouts');

        $completedQuery = (clone $depositsBase)->where('status', 'completed');
        $completedCount = (clone $completedQuery)->count();
        $netCreditedMinor = $this->sumToMinor((clone $completedQuery)->sum('amount'));
        $feesCollectedMinor = $this->sumToMinor((clone $completedQuery)->sum('fee_amount'));
        $grossChargedMinor = $netCreditedMinor + $feesCollectedMinor;

        $depositPendingCount = (clone $depositsBase)->where('status', 'pending')->count();
        $depositFailedCount = (clone $depositsBase)->whereNotIn('status', ['completed', 'pending'])->count();

        $checkoutPendingCount = (clone $checkoutsBase)->where('status', 'pending')->count();
        $checkoutFailedCount = (clone $checkoutsBase)->where('status', '!=', 'pending')->count();

        $pendingCount = $depositPendingCount + $checkoutPendingCount;
        $failedCount = $depositFailedCount + $checkoutFailedCount;

        $depositsTotal = (clone $depositsBase)->count();
        $checkoutsTotal = (clone $checkoutsBase)->count();

        $deposits = $this->viewMode === 'deposits'
            ? (clone $depositsBase)->with(['wallet.user'])->latest('created_at')->latest('id')->paginate(20)
            : null;

        $checkouts = $this->viewMode === 'checkouts'
            ? (clone $checkoutsBase)->with(['wallet.user'])->latest('created_at')->paginate(20)
            : null;

        $providers = collect(['stripe', 'payop'])
            ->merge(Deposit::query()->distinct()->pluck('provider')->filter()->all())
            ->merge(PaymentCheckout::query()->distinct()->pluck('provider')->filter()->all())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $statuses = $this->viewMode === 'checkouts'
            ? ['pending', 'expired', 'failed']
            : collect(['completed', 'pending', 'failed'])
                ->merge(Deposit::query()->distinct()->pluck('status')->filter()->all())
                ->unique()
                ->values()
                ->all();

        $selectedDeposit = null;
        $selectedLedgerEntry = null;
        $selectedCheckout = null;

        if ($this->showDetailModal && $this->selectedDepositId !== null) {
            $selectedDeposit = Deposit::query()
                ->with(['wallet.user'])
                ->find($this->selectedDepositId);

            if ($selectedDeposit !== null) {
                $selectedLedgerEntry = LedgerEntry::query()
                    ->where('reference_type', Deposit::class)
                    ->where('reference_id', $selectedDeposit->id)
                    ->first();

                if ($selectedDeposit->provider_reference !== null && $selectedDeposit->provider_reference !== '') {
                    $selectedCheckout = PaymentCheckout::query()
                        ->with('gatewayConfiguration')
                        ->where('provider', $selectedDeposit->provider)
                        ->where('provider_reference', $selectedDeposit->provider_reference)
                        ->first();
                }
            }
        } elseif ($this->showDetailModal && $this->selectedCheckoutId !== null) {
            $selectedCheckout = PaymentCheckout::query()
                ->with(['wallet.user', 'gatewayConfiguration'])
                ->find($this->selectedCheckoutId);
        }

        return view('livewire.admin.deposit-admin', [
            'deposits' => $deposits,
            'checkouts' => $checkouts,
            'depositsTotal' => $depositsTotal,
            'checkoutsTotal' => $checkoutsTotal,
            'providers' => $providers,
            'statuses' => $statuses,
            'lastSyncedAt' => now()->format('H:i:s'),
            'summary' => [
                'completed_count' => $completedCount,
                'gross_charged' => DecimalMoney::format($grossChargedMinor),
                'fees_collected' => DecimalMoney::format($feesCollectedMinor),
                'net_credited' => DecimalMoney::format($netCreditedMinor),
                'pending_count' => $pendingCount,
                'failed_count' => $failedCount,
                'live_pending_checkouts_count' => $checkoutPendingCount,
            ],
            'selectedDeposit' => $selectedDeposit,
            'selectedLedgerEntry' => $selectedLedgerEntry,
            'selectedCheckout' => $selectedCheckout,
        ])->layoutData(['admin_title' => __('Deposit History')]);
    }
}
