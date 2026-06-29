<?php

declare(strict_types=1);

namespace App\Livewire\Policies;

use App\Modules\CMS\Models\PolicyPage;
use Livewire\Component;

class PolicyIndex extends Component
{
    public function render()
    {
        $policies = PolicyPage::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('livewire.policies.policy-index', [
            'policies' => $policies,
        ])->layout('components.layouts.landing', [
            'title' => 'Policies | PlayerSaloons',
        ]);
    }
}
