<?php

declare(strict_types=1);

namespace App\Livewire\Wallet;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class WalletBalance extends Component
{
    #[On('wallet-balance-updated')]
    public function refreshBalance(): void {}

    public function render()
    {
        return view('livewire.wallet.wallet-balance', [
            'balance' => Auth::user()?->wallet()->value('cached_balance') ?? '0.00',
        ]);
    }
}
