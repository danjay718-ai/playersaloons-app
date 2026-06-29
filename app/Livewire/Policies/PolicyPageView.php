<?php

declare(strict_types=1);

namespace App\Livewire\Policies;

use App\Modules\CMS\Models\PolicyPage;
use Livewire\Component;

class PolicyPageView extends Component
{
    public string $slug;

    public function mount(string $slug): void
    {
        $this->slug = $slug;
    }

    public function render()
    {
        $policy = PolicyPage::query()
            ->where('slug', $this->slug)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->firstOrFail();

        return view('livewire.policies.policy-page-view', [
            'policy' => $policy,
        ])->layout('components.layouts.landing', [
            'title' => $policy->title.' | PlayerSaloons',
        ]);
    }
}
