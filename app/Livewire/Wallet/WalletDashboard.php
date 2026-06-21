<?php

declare(strict_types=1);

namespace App\Livewire\Wallet;

use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class WalletDashboard extends Component
{
    use WithPagination;

    public string $amount = '';

    public string $depositAmount = '';

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $adminRoles = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'];
            if ($user->hasAnyRole($adminRoles)) {
                $this->redirect('/admin');
            }
        }
    }

    public function deposit(WalletService $walletService)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->to('/login');
        }

        $this->validate([
            'depositAmount' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $wallet = $user->wallet()->first();
        if (! $wallet) {
            session()->flash('error', 'Wallet not found.');

            return;
        }

        try {
            $walletService->credit(
                $wallet,
                (float) $this->depositAmount,
                LedgerType::DEPOSIT,
                Wallet::class,
                (string) $wallet->id,
                'Mock Deposit via Web Dashboard'
            );

            session()->flash('message', 'Successfully deposited $'.number_format((float) $this->depositAmount, 2).' to your wallet!');
            $this->reset('depositAmount');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function withdraw(RequestWithdrawalAction $action)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->to('/login');
        }

        $this->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $action->execute($user, (float) $this->amount);
            session()->flash('message', 'Withdrawal request of $'.number_format((float) $this->amount, 2).' submitted successfully and is pending review!');
            $this->reset('amount');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->to('/login');
        }

        $wallet = $user->wallet()->first();
        $ledgerEntries = collect();

        if ($wallet) {
            $ledgerEntries = $wallet->ledgerEntries()
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }

        return view('livewire.wallet.wallet-dashboard', [
            'wallet' => $wallet,
            'ledgerEntries' => $ledgerEntries,
        ])->layout('components.layouts.dashboard', [
            'title' => 'Financial Terminal | PlayerSaloons',
            'dashboard_title' => 'FINANCIAL TERMINAL',
        ]);
    }
}
