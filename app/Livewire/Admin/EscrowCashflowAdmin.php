<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\LedgerType;
use Illuminate\Database\Eloquent\Builder;
use Livewire\WithPagination;

final class EscrowCashflowAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    protected $paginationTheme = 'tailwind';

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('withdrawals.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $platformUser = User::query()->with('wallet')->where('email', 'platform@playersaloons.com')->first();
        $platformWalletId = $platformUser?->wallet?->id;
        $platformPosition = (float) ($platformUser?->wallet?->cached_balance ?? 0);
        $userWalletLiability = (float) Wallet::query()
            ->when($platformWalletId !== null, fn (Builder $query) => $query->where('id', '!=', $platformWalletId))
            ->sum('cached_balance');
        $sponsoredCommitments = (float) Tournament::query()
            ->where('prize_funding_mode', 'sponsored')
            ->where('funding_state', 'reserved')
            ->sum('reserved_prize_amount');

        $flowQuery = $this->filteredLedgerQuery();
        $flowByType = (clone $flowQuery)
            ->selectRaw('type, SUM(amount) as net_amount, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as inflow, SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as outflow, COUNT(*) as entry_count')
            ->groupBy('type')
            ->orderBy('type')
            ->get()
            ->keyBy(fn (LedgerEntry $entry) => $entry->type->value);

        $entries = $this->filteredLedgerQuery()
            ->with('wallet.user')
            ->latest('created_at')
            ->latest('id')
            ->paginate(25);

        $commitments = Tournament::query()
            ->where('prize_funding_mode', 'sponsored')
            ->whereIn('funding_state', ['reserved', 'paid', 'released'])
            ->latest('start_at')
            ->limit(20)
            ->get();

        return view('livewire.admin.escrow-cashflow-admin', [
            'entries' => $entries,
            'flowByType' => $flowByType,
            'commitments' => $commitments,
            'ledgerTypes' => LedgerType::cases(),
            'userWalletLiability' => $userWalletLiability,
            'platformPosition' => $platformPosition,
            'sponsoredCommitments' => $sponsoredCommitments,
        ])->layout('components.layouts.admin', ['admin_title' => 'Escrow & Cashflow']);
    }

    /** @return Builder<LedgerEntry> */
    private function filteredLedgerQuery(): Builder
    {
        return LedgerEntry::query()
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(function (Builder $nested) use ($term): void {
                    $nested->where('description', 'like', $term)
                        ->orWhere('reference_type', 'like', $term)
                        ->orWhere('reference_id', 'like', $term)
                        ->orWhereHas('wallet.user', fn (Builder $user) => $user
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            });
    }
}
